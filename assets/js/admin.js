( function () {
	'use strict';

	// Membership rule editor: "who can book it" and "member discount" level pickers — a dropdown
	// to add a level plus a removable chip per level, instead of a checkbox per PMPro level (which
	// doesn't scale once a site has more than a handful of them). The discount picker's chips also
	// carry their own per-level amount + a "use default" toggle.
	document.querySelectorAll( '.clio-cent-pmpro-level-picker' ).forEach( function ( picker ) {
		var list     = picker.querySelector( '[data-level-list]' );
		var select   = picker.querySelector( '[data-level-select]' );
		var template = picker.querySelector( '[data-level-template]' );

		if ( ! list || ! select || ! template ) {
			return;
		}

		function selectedIds() {
			return Array.prototype.map.call( list.querySelectorAll( '.clio-cent-pmpro-chip' ), function ( chip ) {
				return chip.getAttribute( 'data-id' );
			} );
		}

		function syncOptions() {
			var ids = selectedIds();

			Array.prototype.forEach.call( select.options, function ( option ) {
				if ( option.value ) {
					option.disabled = ids.indexOf( option.value ) !== -1;
				}
			} );
		}

		function escapeHtml( text ) {
			var div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		}

		select.addEventListener( 'change', function () {
			var id = select.value;

			if ( ! id ) {
				return;
			}

			var option = select.options[ select.selectedIndex ];
			var label  = option.getAttribute( 'data-label' ) || option.textContent;

			var html = template.innerHTML
				.split( '__ID__' ).join( id )
				.split( '__LABEL__' ).join( escapeHtml( label ) );

			var wrapper = document.createElement( 'div' );
			wrapper.innerHTML = html.trim();

			if ( wrapper.firstElementChild ) {
				list.appendChild( wrapper.firstElementChild );
			}

			select.value = '';
			syncOptions();
		} );

		list.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest( '[data-level-remove]' ) ) {
				return;
			}

			event.target.closest( '.clio-cent-pmpro-chip' ).remove();
			syncOptions();
		} );

		// "Use default" checkbox: hide/show that chip's own type+amount fields.
		list.addEventListener( 'change', function ( event ) {
			var checkbox = event.target.closest( '[data-use-default]' );

			if ( ! checkbox ) {
				return;
			}

			var amount = checkbox.closest( '.clio-cent-pmpro-chip' ).querySelector( '.clio-cent-pmpro-chip-amount' );

			if ( amount ) {
				amount.hidden = checkbox.checked;
			}
		} );

		syncOptions();
	} );

	// Each "who can book it" / "member discount" column has its own none/any/specific radios;
	// show only the section(s) that apply to whichever is currently checked, instead of always
	// showing every block regardless of the selection.
	document.querySelectorAll( '.clio-cent-pmpro-col' ).forEach( function ( col ) {
		var radios   = col.querySelectorAll( '[data-mode-radio]' );
		var sections = col.querySelectorAll( '[data-show-when]' );

		if ( ! radios.length || ! sections.length ) {
			return;
		}

		function sync() {
			var checked = col.querySelector( '[data-mode-radio]:checked' );
			var value   = checked ? checked.value : '';

			sections.forEach( function ( section ) {
				section.hidden = section.getAttribute( 'data-show-when' ) !== value;
			} );
		}

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', sync );
		} );

		sync();
	} );
}() );
