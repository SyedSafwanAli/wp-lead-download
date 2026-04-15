/* WP Lead Download v1.0.0 | GPL v2 */
/**
 * WP Lead Download — Front-end Script
 * Entire file is inside an IIFE. No variables leak to global scope.
 * Data is accessed via the wld_vars global set by wp_localize_script().
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------
	   DOM references
	------------------------------------------------------------------ */
	var overlay    = document.getElementById( 'wld-modal-overlay' );
	var closeBtn   = document.querySelector( '.wld-close-btn' );
	var screen1    = document.getElementById( 'wld-screen-1' );
	var screen2    = document.getElementById( 'wld-screen-2' );
	var screen3    = document.getElementById( 'wld-screen-3' );
	var leadForm   = document.getElementById( 'wld-lead-form' );
	var otpForm    = document.getElementById( 'wld-otp-form' );
	var s1Error    = document.getElementById( 'wld-s1-error' );
	var s2Error    = document.getElementById( 'wld-s2-error' );
	var s1Submit   = document.getElementById( 'wld-s1-submit' );
	var s2Submit   = document.getElementById( 'wld-s2-submit' );
	var otpInput   = document.getElementById( 'wld-otp-input' );
	var resendLink  = document.getElementById( 'wld-resend-link' );
	var backLink    = document.getElementById( 'wld-back-link' );
	var countdown   = document.getElementById( 'wld-countdown' );
	var modalTitle  = document.getElementById( 'wld-modal-title' );
	var thankYouEl  = document.getElementById( 'wld-thankyou-msg' );
	var emailDisp   = document.getElementById( 'wld-otp-email-display' );
	var testOtpBox  = document.getElementById( 'wld-test-otp-box' );
	var testOtpCode = document.getElementById( 'wld-test-otp-code' );

	// Guard: modal may not exist on this page
	if ( ! overlay ) return;

	/* ------------------------------------------------------------------
	   State
	------------------------------------------------------------------ */
	var currentEmail   = '';
	var countdownTimer = null;
	var previousFocus  = null;

	/* ------------------------------------------------------------------
	   Helpers: screens
	------------------------------------------------------------------ */
	function showScreen( n ) {
		[ screen1, screen2, screen3 ].forEach( function ( s ) { s.style.display = 'none'; } );
		var target = [ null, screen1, screen2, screen3 ][ n ];
		if ( target ) target.style.display = '';
	}

	/* ------------------------------------------------------------------
	   Helpers: error messages
	------------------------------------------------------------------ */
	function showError( el, msg ) {
		el.textContent   = msg;
		el.style.display = 'block';
	}

	function hideError( el ) {
		el.style.display = 'none';
		el.textContent   = '';
	}

	/* ------------------------------------------------------------------
	   Helpers: loading state
	------------------------------------------------------------------ */
	function setLoading( btn, loading ) {
		btn.disabled = loading;
		var text    = btn.querySelector( '.wld-btn-text' );
		var spinner = btn.querySelector( '.wld-spinner' );
		if ( text )    text.style.display    = loading ? 'none' : '';
		if ( spinner ) spinner.style.display = loading ? ''     : 'none';
	}

	/* ------------------------------------------------------------------
	   Helpers: force-download via hidden form + hidden iframe.
	   POST goes to the PHP proxy which responds with Content-Disposition: attachment.
	   Works in ALL browsers including Safari — no fetch/blob/popup issues.
	------------------------------------------------------------------ */
	function forceDownload( downloadId, email ) {
		var frameName = 'wld_dl_' + Date.now();

		// Hidden iframe — receives the file response without navigating the page.
		var iframe = document.createElement( 'iframe' );
		iframe.name             = frameName;
		iframe.style.display    = 'none';
		iframe.style.width      = '0';
		iframe.style.height     = '0';
		document.body.appendChild( iframe );

		// Hidden form — posts credentials to the PHP download endpoint.
		var form    = document.createElement( 'form' );
		form.method = 'POST';
		form.action = wld_vars.ajax_url;
		form.target = frameName;

		var fields = {
			action:      'wld_download_file',
			nonce:       wld_vars.nonce,
			download_id: downloadId,
			email:       email
		};

		Object.keys( fields ).forEach( function ( key ) {
			var input   = document.createElement( 'input' );
			input.type  = 'hidden';
			input.name  = key;
			input.value = fields[ key ];
			form.appendChild( input );
		} );

		document.body.appendChild( form );
		form.submit();

		// Clean up after the download has had time to start.
		setTimeout( function () {
			if ( form.parentNode )   form.parentNode.removeChild( form );
			if ( iframe.parentNode ) iframe.parentNode.removeChild( iframe );
		}, 10000 );
	}

	/* ------------------------------------------------------------------
	   Helpers: localStorage — remember verified email & downloaded IDs
	------------------------------------------------------------------ */
	function getSavedEmail() {
		try { return localStorage.getItem( 'wld_email' ) || ''; } catch(e) { return ''; }
	}

	function saveEmail( email ) {
		try { localStorage.setItem( 'wld_email', email ); } catch(e) {}
	}

	function getDownloadedIds() {
		try { return JSON.parse( localStorage.getItem( 'wld_downloaded' ) || '[]' ); } catch(e) { return []; }
	}

	function markDownloaded( downloadId ) {
		try {
			var ids = getDownloadedIds();
			if ( ids.indexOf( String( downloadId ) ) === -1 ) {
				ids.push( String( downloadId ) );
				localStorage.setItem( 'wld_downloaded', JSON.stringify( ids ) );
			}
		} catch(e) {}
	}

	function hasDownloadedBefore( downloadId ) {
		return getDownloadedIds().indexOf( String( downloadId ) ) !== -1;
	}

	/* ------------------------------------------------------------------
	   Helpers: fetch-based AJAX
	------------------------------------------------------------------ */
	function doAjax( action, data, onSuccess, onError ) {
		var formData = new FormData();
		formData.append( 'action', action );
		formData.append( 'nonce',  wld_vars.nonce );

		Object.keys( data ).forEach( function ( key ) {
			formData.append( key, data[ key ] );
		} );

		fetch( wld_vars.ajax_url, { method: 'POST', body: formData } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res.success ) {
					onSuccess( res.data );
				} else {
					onError( ( res.data && res.data.message ) ? res.data.message : 'Something went wrong.' );
				}
			} )
			.catch( function () {
				onError( 'Connection error. Please try again.' );
			} );
	}

	/* ------------------------------------------------------------------
	   Helpers: nonce refresh (for full-page-cached sites)
	   If wld_vars.nonce is empty (page was cached), fetch a fresh one
	   before the first real AJAX call.
	------------------------------------------------------------------ */
	function fetchNonce( callback ) {
		if ( wld_vars.nonce ) {
			callback();
			return;
		}
		var fd = new FormData();
		fd.append( 'action', 'wld_get_nonce' );
		fetch( wld_vars.ajax_url, { method: 'POST', body: fd } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res.success ) { wld_vars.nonce = res.data.nonce; }
				callback();
			} )
			.catch( function () { callback(); } );
	}

	/* ------------------------------------------------------------------
	   Helpers: focus trap — returns all visible focusable elements
	   inside the modal box
	------------------------------------------------------------------ */
	function getFocusable() {
		var selector = 'a[href], button:not([disabled]), input:not([disabled]), '
			+ 'textarea, select, [tabindex]:not([tabindex="-1"])';
		return Array.prototype.slice.call(
			overlay.querySelectorAll( selector )
		).filter( function ( el ) { return el.offsetParent !== null; } );
	}

	/* ------------------------------------------------------------------
	   Open modal — pre-fills known email, sets hidden fields, applies color
	------------------------------------------------------------------ */
	function openModal( downloadId, formTitle, thankYou, btnColor ) {
		document.getElementById( 'wld-download-id' ).value     = downloadId;
		document.getElementById( 'wld-otp-download-id' ).value = downloadId;
		if ( modalTitle ) modalTitle.textContent = formTitle;
		if ( thankYouEl ) thankYouEl.textContent = thankYou;

		// Apply button color to modal form buttons via CSS custom property
		if ( btnColor ) {
			overlay.style.setProperty( '--wld-color', btnColor );
		}

		// Pre-fill email if we already know it from a previous download
		var savedEmail = getSavedEmail();
		var emailInput = leadForm.querySelector( '[name="email"]' );
		if ( savedEmail && emailInput && ! emailInput.value ) {
			emailInput.value = savedEmail;
		}

		showScreen( 1 );
		overlay.classList.add( 'wld-open' );

		var firstInput = screen1.querySelector( 'input:not([type="hidden"]):not([value])' );
		if ( ! firstInput ) firstInput = screen1.querySelector( 'input:not([type="hidden"])' );
		if ( firstInput ) firstInput.focus();
	}

	/* ------------------------------------------------------------------
	   Button click — returning user shortcut or normal modal flow
	------------------------------------------------------------------ */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.wld-trigger-btn' );
		if ( ! btn ) return;

		previousFocus = document.activeElement;

		var downloadId = btn.getAttribute( 'data-download-id' );
		var formTitle  = btn.getAttribute( 'data-form-title' ) || '';
		var thankYou   = btn.getAttribute( 'data-thankyou' )   || '';
		var btnColor   = btn.getAttribute( 'data-btn-color' )  || '';

		fetchNonce( function () {
			var savedEmail = getSavedEmail();

			// Returning user: same email already downloaded this exact file
			if ( savedEmail && hasDownloadedBefore( downloadId ) ) {
				doAjax(
					'wld_returning_user',
					{ email: savedEmail, download_id: downloadId },
					function () {
						// Confirmed in DB — direct download via PHP proxy
						forceDownload( downloadId, savedEmail );
					},
					function () {
						// Not found in DB (e.g. different device/browser) — open modal
						openModal( downloadId, formTitle, thankYou, btnColor );
					}
				);
				return;
			}

			// First-time or different download — open modal (email pre-filled if known)
			openModal( downloadId, formTitle, thankYou, btnColor );
		} );
	} );

	/* ------------------------------------------------------------------
	   Close modal
	------------------------------------------------------------------ */
	function closeModal() {
		overlay.classList.remove( 'wld-open' );
		resetAll();
		if ( previousFocus && previousFocus.focus ) {
			previousFocus.focus();
		}
		previousFocus = null;
	}

	closeBtn.addEventListener( 'click', closeModal );

	overlay.addEventListener( 'click', function ( e ) {
		if ( e.target === overlay ) closeModal();
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( ! overlay.classList.contains( 'wld-open' ) ) return;

		if ( e.key === 'Escape' ) {
			closeModal();
			return;
		}

		// Focus trap: keep Tab key cycling within the modal
		if ( e.key === 'Tab' ) {
			var focusable = getFocusable();
			if ( ! focusable.length ) { e.preventDefault(); return; }

			var first = focusable[ 0 ];
			var last  = focusable[ focusable.length - 1 ];

			if ( e.shiftKey ) {
				if ( document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				}
			} else {
				if ( document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		}
	} );

	/* ------------------------------------------------------------------
	   Screen 1 — submit lead form, request OTP
	------------------------------------------------------------------ */
	leadForm.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		hideError( s1Error );
		setLoading( s1Submit, true );

		var downloadId = document.getElementById( 'wld-download-id' ).value;
		var fullName   = leadForm.querySelector( '[name="full_name"]' ).value.trim();
		var email      = leadForm.querySelector( '[name="email"]' ).value.trim();
		var phone      = leadForm.querySelector( '[name="phone"]' ).value.trim();

		doAjax(
			'wld_submit_lead',
			{ download_id: downloadId, full_name: fullName, email: email, phone: phone },
			function ( data ) {
				setLoading( s1Submit, false );
				currentEmail = email;

				document.getElementById( 'wld-otp-email' ).value = email;
				if ( emailDisp ) emailDisp.textContent = email;

				// Test Mode: OTP returned directly — show it and auto-fill the input.
				if ( data.test_otp && testOtpBox && testOtpCode ) {
					testOtpCode.textContent = data.test_otp;
					testOtpBox.style.display = '';
					if ( otpInput ) otpInput.value = data.test_otp;
				} else if ( testOtpBox ) {
					testOtpBox.style.display = 'none';
				}

				showScreen( 2 );
				startCountdown( 60 );
				if ( otpInput ) otpInput.focus();
			},
			function ( msg ) {
				setLoading( s1Submit, false );
				showError( s1Error, msg );
			}
		);
	} );

	/* ------------------------------------------------------------------
	   Screen 2 — verify OTP
	------------------------------------------------------------------ */
	otpForm.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		hideError( s2Error );
		setLoading( s2Submit, true );

		var downloadId = document.getElementById( 'wld-otp-download-id' ).value;
		var email      = document.getElementById( 'wld-otp-email' ).value;
		var otpCode    = otpInput.value.trim();

		doAjax(
			'wld_verify_otp',
			{ download_id: downloadId, email: email, otp_code: otpCode },
			function () {
				setLoading( s2Submit, false );

				// Remember this email and mark this download as completed
				saveEmail( email );
				markDownloaded( downloadId );

				showScreen( 3 );

				// Force-download via PHP proxy after a short delay
				setTimeout( function () {
					forceDownload( downloadId, email );
				}, 800 );
			},
			function ( msg ) {
				setLoading( s2Submit, false );
				showError( s2Error, msg );

				// Shake the OTP input to signal the error
				otpInput.classList.add( 'wld-shake' );
				setTimeout( function () {
					otpInput.classList.remove( 'wld-shake' );
				}, 500 );
			}
		);
	} );

	/* ------------------------------------------------------------------
	   OTP input — strip non-digits, auto-submit on 6 chars
	------------------------------------------------------------------ */
	otpInput.addEventListener( 'input', function () {
		otpInput.value = otpInput.value.replace( /[^0-9]/g, '' ).slice( 0, 6 );
		if ( otpInput.value.length === 6 ) {
			otpForm.dispatchEvent( new Event( 'submit' ) );
		}
	} );

	/* ------------------------------------------------------------------
	   Resend OTP
	------------------------------------------------------------------ */
	resendLink.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		if ( resendLink.classList.contains( 'wld-resend-disabled' ) ) return;

		hideError( s2Error );

		var downloadId = document.getElementById( 'wld-otp-download-id' ).value;

		doAjax(
			'wld_resend_otp',
			{ download_id: downloadId, email: currentEmail },
			function ( _data ) {
				startCountdown( 60 );
			},
			function ( msg ) {
				showError( s2Error, msg );
			}
		);
	} );

	/* ------------------------------------------------------------------
	   Back link — return to Screen 1 without clearing name/email
	------------------------------------------------------------------ */
	backLink.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		hideError( s1Error );
		hideError( s2Error );
		otpInput.value = '';
		clearInterval( countdownTimer );
		showScreen( 1 );
	} );

	/* ------------------------------------------------------------------
	   Countdown timer for resend link
	------------------------------------------------------------------ */
	function startCountdown( seconds ) {
		clearInterval( countdownTimer );
		resendLink.classList.add( 'wld-resend-disabled' );

		var remaining = seconds;
		if ( countdown ) countdown.textContent = remaining;

		// Rebuild the resend link text to include the counter
		resendLink.innerHTML = 'Resend in <span id="wld-countdown">' + remaining + '</span>s';
		countdown = document.getElementById( 'wld-countdown' );

		countdownTimer = setInterval( function () {
			remaining--;
			if ( countdown ) countdown.textContent = remaining;

			if ( remaining <= 0 ) {
				clearInterval( countdownTimer );
				resendLink.classList.remove( 'wld-resend-disabled' );
				resendLink.textContent = 'Resend OTP';
			}
		}, 1000 );
	}

	/* ------------------------------------------------------------------
	   Reset everything when modal closes
	------------------------------------------------------------------ */
	function resetAll() {
		leadForm.reset();
		otpForm.reset();
		otpInput.value = '';
		hideError( s1Error );
		hideError( s2Error );
		setLoading( s1Submit, false );
		setLoading( s2Submit, false );
		clearInterval( countdownTimer );
		currentEmail   = '';
		if ( testOtpBox )  testOtpBox.style.display = 'none';
		if ( testOtpCode ) testOtpCode.textContent  = '';
		showScreen( 1 );
	}

} )();
