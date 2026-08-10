<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Fields\Groups;

/**
 * Publication fields (SPEC §5.1, §13 steps 18–20; P21). A publication is a primary
 * accountability document (annual report, financial statement, programme report,
 * newsletter) offered as a downloadable PDF with an on-page text summary — so the
 * content is indexable and readable without opening the file (SPEC §9, §13).
 *
 * The impact figures a funder reads live on the teda/impact-dashboard block and each
 * links back to its source publication; a publication's own summary is what makes
 * that link meaningful.
 */
final class Publication {

	/**
	 * @return array<string, mixed>
	 */
	public static function definition(): array {
		return array(
			'id'           => 'teda_publication_details',
			'title'        => __( 'Publication details', 'teda-core' ),
			'post_types'   => array( 'teda_publication' ),
			'context'      => 'normal',
			'priority'     => 'high',
			'show_in_rest' => true,
			'fields'       => array(
				array(
					'name'         => __( 'Year', 'teda-core' ),
					'id'           => 'teda_pub_year',
					'type'         => 'number',
					'min'          => 2000,
					'max'          => 2100,
					'show_in_rest' => true,
					'desc'         => __( 'The year this document covers or was published. The archive groups documents by this year.', 'teda-core' ),
				),
				array(
					'name'             => __( 'PDF file', 'teda-core' ),
					'id'               => 'teda_pub_file',
					'type'             => 'file_advanced',
					'max_file_uploads' => 1,
					'mime_type'        => 'application/pdf',
					'show_in_rest'     => true,
					'desc'             => __( 'Upload the PDF. Its file size is shown automatically next to the download link, so a reader on mobile data can decide before tapping.', 'teda-core' ),
				),
				array(
					'name'         => __( 'Summary', 'teda-core' ),
					'id'           => 'teda_pub_summary',
					'type'         => 'textarea',
					'rows'         => 5,
					'show_in_rest' => true,
					'desc'         => __( 'A plain-English summary of what the document says. This shows on the page so people and search engines can read the substance without downloading the PDF.', 'teda-core' ),
				),
			),
		);
	}
}
