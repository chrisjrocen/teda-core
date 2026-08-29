/**
 * TEDA blocks — the one editor script (D4: no JSX, no build).
 *
 * For every TEDA block (fed in via wp_localize_script as `tedaBlocks`), this
 * registers a client-side block whose edit() shows a ServerSideRender preview and
 * auto-generates InspectorControls from the block.json attribute schema. save()
 * returns null — these are dynamic (server-rendered) blocks.
 *
 * An attribute may carry a `teda` hint object in block.json to steer the control
 * and group it under a panel, e.g.
 *   "s1_image": { "type": "integer", "default": 0, "teda": { "control": "media", "group": "Slide 1" } }
 * Recognised controls: media | url | textarea (else derived from `type`:
 * boolean → toggle, number/integer → number, string → text). `group` names the
 * PanelBody an attribute lives under; ungrouped attributes share one default panel.
 *
 * teda/tabs and teda/tabs-item hold arbitrary nested blocks per tab, so they're
 * hand-registered below with real InnerBlocks edit()/save() (still no JSX, still
 * no build step) instead of going through the generic attribute-driven loop.
 *
 * teda/donate is a thin placement marker (its content — tiers, goals, trust
 * copy — is admin-managed under Donations → Campaigns, not block attributes)
 * with a single `campaign_id` attribute. It stays in the generic loop below;
 * that attribute's `teda.control: "campaign_picker"` hint renders a SelectControl
 * sourced from `window.tedaCampaigns` (localized by Blocks\Registry) instead of
 * the usual text/number/toggle controls.
 */
(function (wp, data) {
	if (!wp || !wp.blocks || !data) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var SSR = wp.serverSideRender;
	var be = wp.blockEditor;
	var InspectorControls = be.InspectorControls;
	var MediaUpload = be.MediaUpload;
	var MediaUploadCheck = be.MediaUploadCheck;
	var InnerBlocks = be.InnerBlocks;
	var useBlockProps = be.useBlockProps;
	var useInnerBlocksProps = be.useInnerBlocksProps;
	var RichText = be.RichText;
	var cmp = wp.components;
	var PanelBody = cmp.PanelBody;
	var TextControl = cmp.TextControl;
	var TextareaControl = cmp.TextareaControl;
	var ToggleControl = cmp.ToggleControl;
	var SelectControl = cmp.SelectControl;
	var Button = cmp.Button;
	var __ = wp.i18n.__;

	function humanize(key) {
		return key
			.replace(/[_-]+/g, ' ')
			.replace(/([a-z])([A-Z])/g, '$1 $2')
			.replace(/^./, function (c) {
				return c.toUpperCase();
			});
	}

	function hint(schema, name) {
		return schema && schema.teda && schema.teda[name] ? schema.teda[name] : '';
	}

	/**
	 * A single image-media control (select/replace/remove), shared by the
	 * generic attribute-driven loop (control: "media" in block.json) and any
	 * hand-registered block's edit() that needs the same widget.
	 */
	function mediaControl(key, label, value, onChange) {
		return el(
			MediaUploadCheck,
			{ key: key },
			el(
				'div',
				{ className: 'teda-media-control' },
				el('span', { className: 'teda-media-control__label' }, label),
				el(MediaUpload, {
					allowedTypes: ['image'],
					value: value || 0,
					onSelect: function (m) {
						onChange(m && m.id ? m.id : 0);
					},
					render: function (o) {
						return el(
							Fragment,
							{},
							el(
								Button,
								{ variant: 'secondary', onClick: o.open },
								value ? __('Replace image', 'teda-core') : __('Select image', 'teda-core')
							),
							value ? el('span', { className: 'teda-media-control__id' }, ' #' + value) : null,
							value
								? el(
										Button,
										{
											variant: 'link',
											isDestructive: true,
											onClick: function () {
												onChange(0);
											},
										},
										__('Remove', 'teda-core')
								  )
								: null
						);
					},
				})
			)
		);
	}

	function controlFor(key, schema, value, setAttributes) {
		var label = humanize(key);
		var control = hint(schema, 'control');
		var onChange = function (v) {
			var patch = {};
			patch[key] = v;
			setAttributes(patch);
		};

		if (control === 'media') {
			return mediaControl(key, label, value, onChange);
		}
		if (control === 'campaign_picker') {
			var campaigns = Array.isArray(window.tedaCampaigns) ? window.tedaCampaigns : [];
			var options = [{ label: __('— Use the default campaign —', 'teda-core'), value: 0 }].concat(
				campaigns.map(function (c) {
					return { label: c.label, value: c.id };
				})
			);
			return el(SelectControl, {
				key: key,
				label: label,
				value: value || 0,
				options: options,
				onChange: function (v) {
					onChange(parseInt(v, 10) || 0);
				},
			});
		}
		if (control === 'textarea') {
			return el(TextareaControl, { key: key, label: label, rows: 3, value: value || '', onChange: onChange });
		}
		if (control === 'url') {
			return el(TextControl, { key: key, type: 'url', label: label, value: value || '', onChange: onChange });
		}
		if (schema.type === 'boolean') {
			return el(ToggleControl, { key: key, label: label, checked: !!value, onChange: onChange });
		}
		if (schema.type === 'number' || schema.type === 'integer') {
			return el(TextControl, {
				key: key,
				type: 'number',
				label: label,
				value: value === undefined ? '' : value,
				onChange: function (v) {
					onChange(v === '' ? undefined : parseInt(v, 10));
				},
			});
		}
		if (schema.type === 'string' && Array.isArray(schema.enum)) {
			return el(SelectControl, {
				key: key,
				label: label,
				value: value || '',
				options: schema.enum.map(function (v) {
					return { label: v === '' ? __('Default', 'teda-core') : humanize(v), value: v };
				}),
				onChange: onChange,
			});
		}
		if (schema.type === 'string') {
			return el(TextControl, { key: key, label: label, value: value || '', onChange: onChange });
		}
		// Arrays/objects are edited via inner blocks or in the block itself.
		return null;
	}

	/**
	 * teda/tabs — a pure InnerBlocks container restricted to teda/tabs-item
	 * children. The editor shows every tab's content stacked (no simulated
	 * click-to-preview tab switcher) — only the front end (blocks-b.js) builds
	 * the real tablist and shows one panel at a time.
	 */
	function registerTabs() {
		wp.blocks.registerBlockType('teda/tabs', {
			title: __('TEDA Tabs', 'teda-core'),
			category: 'teda',
			icon: 'index-card',
			description: __('Group any blocks under switchable tab headings.', 'teda-core'),
			attributes: {},
			supports: { html: false, align: ['wide'] },
			edit: function () {
				var blockProps = useBlockProps({ className: 'teda-tabs-editor' });
				var innerBlocksProps = useInnerBlocksProps(blockProps, {
					allowedBlocks: ['teda/tabs-item'],
					template: [
						['teda/tabs-item', { label: __('Tab 1', 'teda-core') }],
						['teda/tabs-item', { label: __('Tab 2', 'teda-core') }],
					],
					templateLock: false,
				});
				return el('div', innerBlocksProps);
			},
			save: function () {
				var blockProps = useBlockProps.save({ className: 'teda-tabs' });
				var innerBlocksProps = useInnerBlocksProps.save(blockProps);
				return el('div', innerBlocksProps);
			},
		});
	}

	/**
	 * teda/tabs-item — one tab: an editable label + a fully open InnerBlocks
	 * area (any block, including other teda/* blocks). save() also renders a
	 * visible label heading, so with JS disabled every tab's content shows in
	 * order with a heading — no content is ever hidden without JS to unhide it.
	 */
	function registerTabsItem() {
		wp.blocks.registerBlockType('teda/tabs-item', {
			title: __('TEDA Tab', 'teda-core'),
			category: 'teda',
			icon: 'index-card',
			parent: ['teda/tabs'],
			attributes: { label: { type: 'string', default: 'Tab' } },
			supports: { html: false },
			edit: function (props) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				var blockProps = useBlockProps({ className: 'teda-tabs-editor__item' });
				var innerBlocksProps = useInnerBlocksProps({ className: 'teda-tabs-editor__item-content' }, {});
				return el(
					'div',
					blockProps,
					el(RichText, {
						tagName: 'h3',
						className: 'teda-tabs-editor__item-label',
						value: attributes.label,
						onChange: function (v) {
							setAttributes({ label: v });
						},
						placeholder: __('Tab label', 'teda-core'),
					}),
					el('div', innerBlocksProps)
				);
			},
			save: function (props) {
				var attributes = props.attributes;
				var blockProps = useBlockProps.save({
					className: 'teda-tabs__panel',
					'data-tab-label': attributes.label,
				});
				return el(
					'div',
					blockProps,
					el('h3', { className: 'teda-tabs__panel-label' }, attributes.label),
					el(InnerBlocks.Content)
				);
			},
		});
	}

	/**
	 * Per-block-name config for blocks that stay in the generic attribute-driven
	 * loop below but also need an InnerBlocks area. Each slot gets its own
	 * useInnerBlocksProps() region, rendered in the block's edit() alongside the
	 * normal auto-generated InspectorControls. Currently unused (teda/donate no
	 * longer has InnerBlocks children — its content moved to admin-managed
	 * Campaigns) but left in place as the mechanism for any future block that
	 * needs it, e.g. teda/tabs already proves the "one region" pattern works.
	 */
	var INNER_BLOCKS_CONFIG = {};

	registerTabs();
	registerTabsItem();

	data.forEach(function (b) {
		if (b.name === 'teda/tabs' || b.name === 'teda/tabs-item') {
			return;
		}

		var attributes = b.attributes && typeof b.attributes === 'object' ? b.attributes : {};
		var fallbackGroup = __('TEDA settings', 'teda-core');

		wp.blocks.registerBlockType(b.name, {
			title: b.title,
			category: 'teda',
			icon: b.icon,
			attributes: attributes,
			edit: function (props) {
				// Build controls, bucketed into ordered PanelBody groups.
				var groups = [];
				var index = {};
				Object.keys(attributes).forEach(function (key) {
					var schema = attributes[key];
					var control = controlFor(key, schema, props.attributes[key], props.setAttributes);
					if (!control) {
						return;
					}
					var group = hint(schema, 'group') || fallbackGroup;
					if (index[group] === undefined) {
						index[group] = groups.length;
						groups.push({ title: group, controls: [] });
					}
					groups[index[group]].controls.push(control);
				});

				var panels = groups.map(function (grp, gi) {
					return el(PanelBody, { key: grp.title, title: grp.title, initialOpen: gi === 0 }, grp.controls);
				});

				// Blocks configured in INNER_BLOCKS_CONFIG get one
				// useInnerBlocksProps() region per slot, rendered in the
				// block's own content area — same "always called in the same
				// order for this block type" pattern as any other hook,
				// since `config` is a static lookup by block name.
				var config = INNER_BLOCKS_CONFIG[b.name];
				var innerBlocksAreas = config
					? config.slots.map(function (slot) {
							var innerProps = useInnerBlocksProps(
								{ className: slot.wrapperClass },
								{ allowedBlocks: slot.allowedBlocks, template: slot.template, templateLock: slot.templateLock }
							);
							return el(
								'div',
								{ key: slot.key, className: 'teda-donate-editor__slot' },
								el('h4', { className: 'teda-donate-editor__slot-label' }, slot.label),
								el('div', innerProps)
							);
					  })
					: null;

				return el(
					Fragment,
					{},
					panels.length ? el(InspectorControls, {}, panels) : null,
					innerBlocksAreas,
					el(SSR, { block: b.name, attributes: props.attributes })
				);
			},
			save: function () {
				return null;
			},
		});
	});
})(window.wp, window.tedaBlocks);
