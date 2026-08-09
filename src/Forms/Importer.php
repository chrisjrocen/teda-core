<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Forms;

/**
 * Turns the version-controlled blueprints in forms/*.json into real Fluent Forms
 * (P13 task 2). The blueprints are a small, readable TEDA schema; this class is
 * the only place that knows how to compile that into Fluent Forms' verbose field
 * structure, so the forms are reproducible from source instead of trapped in the
 * database.
 *
 * Import is idempotent: a form is matched by its TEDA slug (stored in form_meta as
 * {@see Fluent_Adapter::SLUG_META}); re-importing updates the same form rather than
 * creating duplicates. Existing entries are never touched.
 *
 * Every compiled form carries three defences baked in (SPEC §8.1, no CAPTCHA):
 *   - a honeypot text field, visually hidden — bots fill it, humans never see it;
 *   - a hidden time-trap field, stamped at render, checked on submit;
 * and the event-registration form additionally carries a hidden, server-validated
 * event id. The spam checks themselves live in {@see Spam}; this class only ensures
 * the fields exist to check.
 */
final class Importer {

	/**
	 * The consent wording, verbatim from SPEC §8.2. Never paraphrase this — it is
	 * the site's Data Protection Act 2019 basis for storing a person's details.
	 */
	public const CONSENT_TEXT = 'I agree that TEDA may store my details to contact me about its programmes. I can ask to be removed at any time by emailing tedayouthteso@gmail.com.';

	/** Hidden field names the Spam guard reads. */
	public const HONEYPOT_FIELD  = 'teda_hp';
	public const TIMESTAMP_FIELD = 'teda_ts';

	/**
	 * Import every blueprint under forms/. Returns a per-slug report of what was
	 * created or updated.
	 *
	 * @return array<string, array{status: string, form_id: int}>
	 */
	public function import_all(): array {
		$report = array();
		foreach ( glob( TEDA_CORE_PATH . 'forms/*.json' ) ?: array() as $file ) {
			$slug = basename( $file, '.json' );
			$out  = $this->import_file( $file );
			if ( null !== $out ) {
				$report[ $slug ] = $out;
			}
		}
		return $report;
	}

	/**
	 * Import one blueprint file. Null when the file is unreadable or malformed
	 * (logged by the caller), so one bad file never aborts the batch.
	 *
	 * @return array{status: string, form_id: int}|null
	 */
	public function import_file( string $file ): ?array {
		$raw = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
		$def = json_decode( $raw, true );
		if ( ! is_array( $def ) || empty( $def['slug'] ) || empty( $def['fields'] ) ) {
			return null;
		}

		$slug    = (string) $def['slug'];
		$adapter = new Fluent_Adapter();
		$existing = $adapter->form_id( $slug );

		$form_fields = wp_json_encode( $this->compile_fields( $def ) );

		global $wpdb;
		$forms = $wpdb->prefix . 'fluentform_forms';
		$now   = current_time( 'mysql' );

		if ( null === $existing ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB
				$forms,
				array(
					'title'       => (string) ( $def['title'] ?? $slug ),
					'status'      => 'published',
					'form_fields' => $form_fields,
					'type'        => 'form',
					'created_by'  => get_current_user_id() ?: 1,
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);
			$form_id = (int) $wpdb->insert_id;
			$status  = 'created';
		} else {
			$form_id = $existing;
			$wpdb->update( // phpcs:ignore WordPress.DB
				$forms,
				array(
					'title'       => (string) ( $def['title'] ?? $slug ),
					'form_fields' => $form_fields,
					'updated_at'  => $now,
				),
				array( 'id' => $form_id )
			);
			$status = 'updated';
		}

		$this->write_meta( $form_id, $slug, $def );

		return array( 'status' => $status, 'form_id' => $form_id );
	}

	/**
	 * Build the Fluent Forms `form_fields` object from a blueprint: the visible
	 * fields, then the two spam-defence hidden fields (and the event id where the
	 * blueprint asks for it), then the submit button.
	 *
	 * @param array<string, mixed> $def Decoded blueprint.
	 * @return array<string, mixed>
	 */
	private function compile_fields( array $def ): array {
		$fields = array();
		$index  = 0;

		foreach ( (array) $def['fields'] as $spec ) {
			$field = $this->compile_field( (array) $spec, $index );
			if ( null !== $field ) {
				$fields[] = $field;
				++$index;
			}
		}

		// Honeypot: a real text input, moved off-screen with a container class the
		// theme hides. No label a screen reader would announce as required.
		$fields[] = $this->text_field(
			self::HONEYPOT_FIELD,
			__( 'Leave this field empty', 'teda-core' ),
			false,
			$index++,
			array( 'container_class' => 'teda-hp', 'help_message' => __( 'If you are human, leave this blank.', 'teda-core' ) )
		);

		// Time-trap: a hidden field stamped with render time by Spam::stamp_form().
		$fields[] = $this->hidden_field( self::TIMESTAMP_FIELD, '', $index++ );

		// Event registration carries the event id, defaulted to the embedding post
		// and re-validated server-side (Spam / Registry) so it cannot be forged.
		if ( ! empty( $def['event'] ) || 'event-registration' === ( $def['slug'] ?? '' ) ) {
			$fields[] = $this->hidden_field( Fluent_Adapter::EVENT_FIELD, '{embed_post.ID}', $index++ );
		}

		return array(
			'fields'       => $fields,
			'submitButton' => $this->submit_button(),
		);
	}

	/**
	 * Compile one blueprint field spec into a Fluent Forms field, or null for an
	 * unknown type.
	 *
	 * @param array<string, mixed> $spec  Blueprint field.
	 * @param int                  $index Position in the form.
	 * @return array<string, mixed>|null
	 */
	private function compile_field( array $spec, int $index ): ?array {
		$type     = (string) ( $spec['type'] ?? 'text' );
		$name     = (string) ( $spec['name'] ?? '' );
		$label    = (string) ( $spec['label'] ?? '' );
		$required = ! empty( $spec['required'] );
		$extra    = array(
			'help_message' => (string) ( $spec['help'] ?? '' ),
			'placeholder'  => (string) ( $spec['placeholder'] ?? '' ),
		);
		$options  = array_map( 'strval', (array) ( $spec['options'] ?? array() ) );

		switch ( $type ) {
			case 'text':
			case 'tel':
				return $this->text_field( $name, $label, $required, $index, $extra + array( 'input_type' => 'tel' === $type ? 'tel' : 'text' ) );
			case 'email':
				return $this->email_field( $name, $label, $required, $index, $extra );
			case 'textarea':
				return $this->textarea_field( $name, $label, $required, $index, $extra );
			case 'select':
				return $this->choice_field( 'select', $name, $label, $required, $options, $index, $extra );
			case 'radio':
				return $this->choice_field( 'input_radio', $name, $label, $required, $options, $index, $extra );
			case 'checkbox':
				return $this->choice_field( 'input_checkbox', $name, $label, $required, $options, $index, $extra );
			case 'consent':
				return $this->consent_field( $name ?: 'consent', $index );
			default:
				return null;
		}
	}

	/* --------------------------------------------------------------------- */
	/* Field builders                                                        */
	/* --------------------------------------------------------------------- */

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function text_field( string $name, string $label, bool $required, int $index, array $extra = array() ): array {
		return array(
			'index'      => $index,
			'element'    => 'input_text',
			'attributes' => array(
				'type'        => (string) ( $extra['input_type'] ?? 'text' ),
				'name'        => $name,
				'value'       => '',
				'id'          => '',
				'class'       => '',
				'placeholder' => (string) ( $extra['placeholder'] ?? '' ),
			),
			'settings'   => array(
				'container_class'  => (string) ( $extra['container_class'] ?? '' ),
				'label'            => $label,
				'label_placement'  => '',
				'help_message'     => (string) ( $extra['help_message'] ?? '' ),
				'admin_field_label' => '',
				'validation_rules' => $this->required_rule( $required ),
				'conditional_logics' => array(),
			),
			'editor_options' => array( 'title' => $label, 'icon_class' => 'ff-edit-text', 'template' => 'inputText' ),
			'uniqElKey'  => 'el_' . $name,
		);
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function email_field( string $name, string $label, bool $required, int $index, array $extra = array() ): array {
		$rules = $this->required_rule( $required );
		$rules['email'] = array( 'value' => true, 'message' => __( 'This field must contain a valid email', 'teda-core' ) );
		return array(
			'index'      => $index,
			'element'    => 'input_email',
			'attributes' => array(
				'type'        => 'email',
				'name'        => $name,
				'value'       => '',
				'id'          => '',
				'class'       => '',
				'placeholder' => (string) ( $extra['placeholder'] ?? '' ),
			),
			'settings'   => array(
				'container_class'  => '',
				'label'            => $label,
				'label_placement'  => '',
				'help_message'     => (string) ( $extra['help_message'] ?? '' ),
				'admin_field_label' => '',
				'validation_rules' => $rules,
				'conditional_logics' => array(),
			),
			'editor_options' => array( 'title' => $label, 'icon_class' => 'ff-edit-email', 'template' => 'inputText' ),
			'uniqElKey'  => 'el_' . $name,
		);
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function textarea_field( string $name, string $label, bool $required, int $index, array $extra = array() ): array {
		return array(
			'index'      => $index,
			'element'    => 'textarea',
			'attributes' => array(
				'name'        => $name,
				'value'       => '',
				'id'          => '',
				'class'       => '',
				'placeholder' => (string) ( $extra['placeholder'] ?? '' ),
				'rows'        => 4,
				'cols'        => 2,
			),
			'settings'   => array(
				'container_class'  => '',
				'label'            => $label,
				'label_placement'  => '',
				'help_message'     => (string) ( $extra['help_message'] ?? '' ),
				'admin_field_label' => '',
				'validation_rules' => $this->required_rule( $required ),
				'conditional_logics' => array(),
			),
			'editor_options' => array( 'title' => $label, 'icon_class' => 'ff-edit-textarea', 'template' => 'inputTextarea' ),
			'uniqElKey'  => 'el_' . $name,
		);
	}

	/**
	 * A select / radio / checkbox from a flat list of option labels.
	 *
	 * @param string[]             $options
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function choice_field( string $element, string $name, string $label, bool $required, array $options, int $index, array $extra = array() ): array {
		$advanced = array();
		foreach ( $options as $opt ) {
			$advanced[] = array( 'label' => $opt, 'value' => $opt, 'calc_value' => '', 'image' => '' );
		}
		$template = 'select' === $element ? 'select' : 'inputCheckable';

		return array(
			'index'      => $index,
			'element'    => $element,
			'attributes' => array(
				'type'  => 'input_checkbox' === $element ? 'checkbox' : ( 'input_radio' === $element ? 'radio' : '' ),
				'name'  => $name,
				'value' => 'input_checkbox' === $element ? array() : '',
			),
			'settings'   => array(
				'container_class'  => '',
				'label'            => $label,
				'admin_field_label' => '',
				'label_placement'  => '',
				'display_type'     => '',
				'help_message'     => (string) ( $extra['help_message'] ?? '' ),
				'placeholder'      => 'select' === $element ? __( '- Select -', 'teda-core' ) : '',
				'advanced_options' => $advanced,
				'validation_rules' => $this->required_rule( $required ),
				'conditional_logics' => array(),
				'randomize_options' => 'no',
				'enable_select_2'  => 'no',
			),
			'editor_options' => array( 'title' => $label, 'template' => $template ),
			'uniqElKey'  => 'el_' . $name,
		);
	}

	/**
	 * The consent checkbox: a single-option checkbox carrying the verbatim SPEC
	 * §8.2 wording, unticked by default (checkboxes have no preselected option) and
	 * required, so a submission without it fails with a clear message.
	 *
	 * @return array<string, mixed>
	 */
	private function consent_field( string $name, int $index ): array {
		return array(
			'index'      => $index,
			'element'    => 'input_checkbox',
			'attributes' => array( 'type' => 'checkbox', 'name' => $name, 'value' => array() ),
			'settings'   => array(
				'container_class'  => 'teda-consent',
				'label'            => __( 'Consent', 'teda-core' ),
				'admin_field_label' => __( 'Consent', 'teda-core' ),
				'label_placement'  => 'hide_label',
				'display_type'     => '',
				'help_message'     => '',
				'advanced_options' => array(
					array( 'label' => self::CONSENT_TEXT, 'value' => 'agreed', 'calc_value' => '', 'image' => '' ),
				),
				'validation_rules' => array(
					'required' => array(
						'value'   => true,
						'message' => __( 'Please tick the box to agree before submitting.', 'teda-core' ),
					),
				),
				'conditional_logics' => array(),
			),
			'editor_options' => array( 'title' => __( 'Consent', 'teda-core' ), 'template' => 'inputCheckable' ),
			'uniqElKey'  => 'el_' . $name,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function hidden_field( string $name, string $value, int $index ): array {
		return array(
			'index'      => $index,
			'element'    => 'input_hidden',
			'attributes' => array( 'type' => 'hidden', 'name' => $name, 'value' => $value ),
			'settings'   => array( 'admin_field_label' => $name, 'validation_rules' => array(), 'conditional_logics' => array() ),
			'editor_options' => array( 'title' => 'Hidden Field', 'template' => 'inputHidden' ),
			'uniqElKey'  => 'el_' . $name,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function submit_button(): array {
		return array(
			'uniqElKey' => 'el_submit',
			'element'   => 'button',
			'attributes' => array( 'type' => 'submit', 'class' => '' ),
			'settings'  => array(
				'align'         => 'left',
				'button_style'  => 'default',
				'container_class' => '',
				'button_size'   => 'md',
				'color'         => '#ffffff',
				'button_ui'     => array( 'type' => 'default', 'text' => __( 'Submit', 'teda-core' ), 'img_url' => '' ),
			),
			'editor_options' => array( 'title' => 'Submit Button' ),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function required_rule( bool $required ): array {
		return array(
			'required' => array(
				'value'   => $required,
				'message' => __( 'This field is required', 'teda-core' ),
			),
		);
	}

	/* --------------------------------------------------------------------- */
	/* Meta                                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * Write the form's settings, confirmation, admin email notification and — the
	 * anchor the rest of P13 relies on — its TEDA slug tag. Idempotent: each key is
	 * replaced, not appended.
	 *
	 * @param array<string, mixed> $def
	 */
	private function write_meta( int $form_id, string $slug, array $def ): void {
		$confirmation = (string) ( $def['confirmation'] ?? __( 'Thank you. Your submission has been received.', 'teda-core' ) );

		$this->put_meta(
			$form_id,
			'formSettings',
			array(
				'confirmation' => array(
					'redirectTo'            => 'samePage',
					'messageToShow'         => $confirmation,
					'samePageFormBehavior'  => 'hide_form',
					'customPage'            => null,
					'customUrl'             => null,
				),
				'restrictions' => array(
					'denyEmptySubmission' => array( 'enabled' => true, 'message' => __( 'Please fill in the form before submitting.', 'teda-core' ) ),
				),
				'layout' => array( 'labelPlacement' => 'top', 'asteriskPlacement' => 'asterisk-right' ),
			)
		);

		$this->put_meta( $form_id, 'template_name', $slug );

		// Admin email notification, so a submission reaches TEDA even before anyone
		// opens the dashboard (acceptance: "send an email").
		$notify = (array) ( $def['notify'] ?? array() );
		$to     = (string) ( $notify['to'] ?? get_option( 'admin_email' ) );
		$this->put_meta(
			$form_id,
			'notifications',
			array(
				'name'    => __( 'Admin notification', 'teda-core' ),
				'sendTo'  => array( 'type' => 'email', 'email' => $to, 'field' => '', 'routing' => array() ),
				'enabled' => true,
				'subject' => (string) ( $notify['subject'] ?? __( 'New form submission', 'teda-core' ) ),
				'to'      => $to,
				'replyTo' => '{wp.admin_email}',
				'message' => '{all_data}',
				'fromName' => '',
				'fromEmail' => '',
			)
		);

		// The stable anchor for Fluent_Adapter::form_id().
		$this->put_meta( $form_id, Fluent_Adapter::SLUG_META, $slug, false );
	}

	/**
	 * Replace a single form_meta row (delete-then-insert), so imports stay
	 * idempotent. $json controls whether the value is JSON-encoded.
	 *
	 * @param mixed $value
	 */
	private function put_meta( int $form_id, string $key, $value, bool $json = true ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_form_meta';
		$wpdb->delete( $table, array( 'form_id' => $form_id, 'meta_key' => $key ) ); // phpcs:ignore WordPress.DB
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'form_id'  => $form_id,
				'meta_key' => $key,
				'value'    => $json ? (string) wp_json_encode( $value ) : (string) $value,
			)
		);
	}
}
