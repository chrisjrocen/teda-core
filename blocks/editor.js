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

	function controlFor(key, schema, value, setAttributes) {
		var label = humanize(key);
		var control = hint(schema, 'control');
		var onChange = function (v) {
			var patch = {};
			patch[key] = v;
			setAttributes(patch);
		};

		if (control === 'media') {
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
					return { label: humanize(v), value: v };
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

	data.forEach(function (b) {
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

				return el(
					Fragment,
					{},
					panels.length ? el(InspectorControls, {}, panels) : null,
					el(SSR, { block: b.name, attributes: props.attributes })
				);
			},
			save: function () {
				return null;
			},
		});
	});
})(window.wp, window.tedaBlocks);
