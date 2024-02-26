class VamtamNavMenu extends elementorModules.frontend.handlers.Base {
	getDefaultSettings() {
		return {
			selectors: {
				navLinks: 'nav.e--pointer-theme-underline a.elementor-item', // top-level links.
				toggle: '.elementor-menu-toggle',
				dropdownMenu: '.elementor-nav-menu__container.elementor-nav-menu--dropdown',
			},
		};
	}

	getDefaultElements() {
		const selectors = this.getSettings( 'selectors' );
		return {
			$navLinks: this.$element.find( selectors.navLinks ),
			$toggle: this.$element.find( selectors.toggle ),
			$dropdownMenu: this.$element.find( selectors.dropdownMenu ),
		};
	}

	onInit( ...args ) {
		super.onInit( ...args );

		// "nav-menu--disable-scroll-on-mobile" feature.
		this.handleMobileDisableScroll();
	}

	handleMobileDisableScroll() {
		const $el = this.$element,
			_this = this;

		let lockedScroll   = false,
			prevIsBelowMax = window.VAMTAM.isBelowMaxDeviceWidth();


		const disableScroll = function ( implicit = false ) {
			// Disable page scroll.
			jQuery( 'html, body' ).addClass( 'vamtam-disable-scroll' );
			if ( ! implicit ) {
				lockedScroll = true;
			}
		};
		const enableScroll = function ( implicit = false ) {
			// Enable page scroll.
			jQuery( 'html, body' ).removeClass( 'vamtam-disable-scroll' );
			if ( ! implicit ) {
				lockedScroll = false;
			}
		};

		const toggleHandler = function ( e ) {
            // Timeout is there so the active class has been toggled accordingly, prior to checking.
            setTimeout( () => {
                if ( e.target.closest( '.vamtam-has-mobile-disable-scroll' ) ) {
                    if ( _this.elements.$toggle.hasClass( 'elementor-active' ) ) {
                        disableScroll();
                    } else {
                        enableScroll();
                    }
                }
            }, 50 );
		}

		var resizeHandler = function () {
			var isBelowMax = window.VAMTAM.isBelowMaxDeviceWidth();
			if ( ( prevIsBelowMax !== isBelowMax ) && lockedScroll ) {
				if ( isBelowMax ) {
					// We are at below-max breakpoint.
					// Disable scroll.
					disableScroll( true );
				} else {
					// We are at max breakpoint.
					// Enable scroll.
					enableScroll( true );
				}
				prevIsBelowMax = isBelowMax;
			}
		};
		if ( $el.hasClass( 'vamtam-has-mobile-disable-scroll' ) ) {
			this.elements.$toggle.on( 'click', toggleHandler );
			window.addEventListener( 'resize', window.VAMTAM.debounce( resizeHandler, 200 ), false );
		}
	}
}


jQuery( window ).on( 'elementor/frontend/init', () => {
	if ( ! elementorFrontend.elementsHandler || ! elementorFrontend.elementsHandler.attachHandler ) {
		const addHandler = ( $element ) => {
			elementorFrontend.elementsHandler.addHandler( VamtamNavMenu, {
				$element,
			} );
		};

		elementorFrontend.hooks.addAction( 'frontend/element_ready/nav-menu.default', addHandler, 100 );
	} else {
		elementorFrontend.elementsHandler.attachHandler( 'nav-menu', VamtamNavMenu );
	}
} );
