var DBTech = window.DBTech || {};
DBTech.Credits = window.DBTech.Credits || {};

((window, document) =>
{
	'use strict'

	// ################################## EDITOR DIALOG ###########################################
	DBTech.Credits.EditorDialogCharge = XF.extend(XF.EditorDialog, {
		_beforeShow: function (overlay)
		{
			document.querySelector('#editor_dbtech_credits_charge_title').value = ''
		},

		_init: function (overlay)
		{
			XF.on(
				document.querySelector('#editor_dbtech_credits_charge_form'),
				'submit',
				this.submit.bind(this)
			)
		},

		submit: function (e)
		{
			e.preventDefault();

			const ed = this.ed
			const overlay = this.overlay

			ed.selection.restore();
			DBTech.Credits.EditorHelpers.insertCharge(
				ed,
				document.querySelector('#editor_dbtech_credits_charge_title').value
			);

			overlay.hide();
		}
	});

	// ################################## EDITOR START ###########################################
	DBTech.Credits.editorStart = {
		startAll ()
		{
			DBTech.Credits.editorStart.registerCommands();
			DBTech.Credits.editorStart.registerDialogs();
		},

		registerCommands ()
		{
			let custom;

			try
			{
				custom = JSON.parse(document.querySelector('.js-editorCustom').innerHTML) || {}
			}
			catch (e)
			{
				console.error(e);
				custom = {};
			}

			if (typeof custom.charge !== 'undefined')
			{
				FroalaEditor.RegisterCommand('xfCustom_charge', {
					title: custom.charge.title,
					icon: 'xfCustom_charge',
					undo: true,
					focus: true,
					callback: function()
					{
						XF.EditorHelpers.loadDialog(this, 'dbtechCreditsCharge');
					}
				});
			}
		},

		registerDialogs ()
		{
			XF.EditorHelpers.dialogs.dbtechCreditsCharge = new DBTech.Credits.EditorDialogCharge('dbtechCreditsCharge');
		}
	};

	// ################################## EDITOR HELPER ###########################################
	DBTech.Credits.EditorHelpers = {
		insertCharge: function(ed, amount)
		{
			let open
			if (amount)
			{
				open = '[CHARGE=' + amount + ']';
			}
			else
			{
				open = '[CHARGE]';
			}

			XF.EditorHelpers.wrapSelectionText(ed, open, '[/CHARGE]', true);
		}
	};

	XF.on(document, 'editor:first-start', DBTech.Credits.editorStart.startAll, { once: true })
})(window, document)