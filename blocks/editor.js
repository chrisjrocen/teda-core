/**
 * TEDA blocks — the one editor script (D4: no JSX, no build).
 *
 * For every TEDA block (fed in via wp_localize_script as `tedaBlocks`), this
 * registers a client-side block whose edit() shows a ServerSideRender preview and
 * auto-generates InspectorControls from the block.json attribute schema. save()
 * returns null — these are dynamic (server-rendered) blocks.
 */
(function (wp, data) {
	if (!wp || !wp.blocks || !data) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var SSR = wp.serverSideRender;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var __ = wp.i18n.__;

	function humanize(key) {
		return key
			.replace(/[_-]+/g, ' ')
			.replace(/([a-z])([A-Z])/g, '$1 $2')
			.replace(/^./, function (c) {
				return c.toUpperCase();
			});
	}

	function controlFor(key, schema, value, setAttributes) {
		var label = humanize(key);
		var onChange = function (v) {
			var patch = {};
			patch[key] = v;
			setAttributes(patch);
		};

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
		if (schema.type === 'string') {
			return el(TextControl, { key: key, label: label, value: value || '', onChange: onChange });
		}
		// Arrays/objects are edited via inner blocks or in the block itself.
		return null;
	}

	data.forEach(function (b) {
		var attributes = b.attributes && typeof b.attributes === 'object' ? b.attributes : {};

		wp.blocks.registerBlockType(b.name, {
			title: b.title,
			category: 'teda',
			icon: b.icon,
			attributes: attributes,
			edit: function (props) {
				var controls = Object.keys(attributes)
					.map(function (key) {
						return controlFor(key, attributes[key], props.attributes[key], props.setAttributes);
					})
					.filter(Boolean);

				return el(
					Fragment,
					{},
					controls.length
						? el(
								InspectorControls,
								{},
								el(PanelBody, { title: __('TEDA settings', 'teda-core'), initialOpen: true }, controls)
						  )
						: null,
					el(SSR, { block: b.name, attributes: props.attributes })
				);
			},
			save: function () {
				return null;
			},
		});
	});
})(window.wp, window.tedaBlocks);
