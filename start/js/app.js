$(function () {
  var formFallbackSelectors = 'iframe, form, input, textarea, select, button, .pipedriveWebForms__form';
  var mobileNumberPattern = /^[6-9]\d{9}$/;
  var showFormFallback = function ($container) {
    if ($container.data('fallbackShown')) {
      return;
    }

    $container.data('fallbackShown', true);
    $container.empty().hide();
    $container.siblings('.form-fallback').removeAttr('hidden');
  };

  var verifyEmbeddedForms = function () {
    $('.pipedriveWebForms').each(function () {
      var $container = $(this);
      if (!$container.find(formFallbackSelectors).length) {
        showFormFallback($container);
      }
    });
  };

  $('a[href^="#"]').on('click', function (e) {
    var target = $($(this).attr('href'));
    if (target.length) {
      e.preventDefault();
      $('html,body').animate({ scrollTop: target.offset().top - 70 }, 500);
    }
  });

  var initMarqueeSlider = function ($track, options) {
    if ($track.length && !$track.hasClass('slick-initialized')) {
      $track.slick(options);
    }
  };

  initMarqueeSlider($('.services-track'), {
    slidesToShow: 4,
    slidesToScroll: 1,
    infinite: true,
    arrows: true,
    dots: false,
    autoplay: true,
    autoplaySpeed: 0,
    speed: 2200,
    cssEase: 'linear',
    pauseOnHover: true,
    pauseOnFocus: true,
    prevArrow: $('.services-marquee .marquee-btn-prev'),
    nextArrow: $('.services-marquee .marquee-btn-next'),
    responsive: [
      {
        breakpoint: 992,
        settings: {
          slidesToShow: 2
        }
      },
      {
        breakpoint: 576,
        settings: {
          slidesToShow: 1
        }
      }
    ]
  });

  initMarqueeSlider($('.testimonials-track'), {
    slidesToShow: 2,
    slidesToScroll: 1,
    infinite: true,
    arrows: true,
    dots: false,
    autoplay: true,
    autoplaySpeed: 0,
    speed: 2200,
    cssEase: 'linear',
    pauseOnHover: true,
    pauseOnFocus: true,
    adaptiveHeight: false,
    prevArrow: $('.testimonials-marquee .marquee-btn-prev'),
    nextArrow: $('.testimonials-marquee .marquee-btn-next'),
    responsive: [
      {
        breakpoint: 992,
        settings: {
          slidesToShow: 1
        }
      }
    ]
  });

  var resumeAfterDelay = function ($track) {
    window.clearTimeout($track.data('resumeTimer'));
    var timer = window.setTimeout(function () {
      if ($track.hasClass('slick-initialized')) {
        $track.slick('slickPlay');
      }
    }, 1000);
    $track.data('resumeTimer', timer);
  };

  $('.services-track, .testimonials-track').on('mouseenter', function () {
    if ($(this).hasClass('slick-initialized')) {
      window.clearTimeout($(this).data('resumeTimer'));
      $(this).slick('slickPause');
    }
  });

  $('.services-track, .testimonials-track').on('mouseleave', function () {
    if ($(this).hasClass('slick-initialized')) {
      $(this).slick('slickPlay');
    }
  });

  $('.services-track, .testimonials-track').on('touchstart pointerdown click', function (e) {
    if (e.type === 'pointerdown' && e.pointerType && e.pointerType !== 'touch') {
      return;
    }

    if ($(this).hasClass('slick-initialized')) {
      $(this).slick('slickPause');
      resumeAfterDelay($(this));
    }
  });

  $('.marquee-btn').on('click', function () {
    var target = $(this).data('target') === 'services' ? $('.services-track') : $('.testimonials-track');
    if (target.hasClass('slick-initialized')) {
      target.slick('slickPause');
      resumeAfterDelay(target);
    }
  });

  var emailPolicy = window.SecureEdgeEmail;
  var attributionKeys = ['gclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
  var readAttribution = function (key) {
    try { return sessionStorage.getItem('se_' + key) || ''; } catch (error) { return ''; }
  };
  var landingParams = new URLSearchParams(window.location.search);
  attributionKeys.forEach(function (key) {
    try { if (landingParams.get(key)) sessionStorage.setItem('se_' + key, landingParams.get(key)); } catch (error) {}
  });

  // Simple URL-encoded POST: no custom headers or preflight, readable JSON only.
  // No no-cors fallback: opaque responses can never confirm success.
  var callBackend = async function (endpoint, values) {
    var controller = new AbortController();
    var timer = window.setTimeout(function () { controller.abort(); }, 45000);
    try {
      var response;
      try {
        response = await fetch(endpoint, {
          method: 'POST', body: new URLSearchParams(values), credentials: 'omit',
          redirect: 'follow', signal: controller.signal
        });
      } catch (error) {
        if (error.name === 'AbortError') throw new Error('The verification service took too long to respond. Please try again; your details have been kept.');
        throw new Error('Unable to read the verification service response. Please check your connection and try again; your details have been kept.');
      }
      if (!response.ok) throw new Error('The verification service is unavailable (HTTP ' + response.status + '). Please try again; your details have been kept.');
      var result;
      try {
        result = await response.json();
      } catch (error) {
        if (error.name === 'AbortError') throw new Error('The verification service took too long to respond. Please try again; your details have been kept.');
        throw new Error('The verification service returned an unreadable response. Please contact support; your details have been kept.');
      }
      if (!result || typeof result.success !== 'boolean' || typeof result.message !== 'string') {
        throw new Error('The verification service returned an unexpected response. Please contact support; your details have been kept.');
      }
      return result;
    } finally { window.clearTimeout(timer); }
  };

  $('.lead-form').each(function () {
    var form = this, $form = $(form);
    var $number = $form.find('[name="number"]'), $email = $form.find('[name="email"]');
    var $send = $form.find('.send-otp'), $verify = $form.find('.verify-otp');
    var $otp = $form.find('.otp-input'), $section = $form.find('.otp-section');
    var $status = $form.find('.form-status').not('.email-status'), $emailStatus = $form.find('.email-status');
    var $submit = $form.find('[type="submit"]'), submitText = $submit.html();
    var token = '', verifiedEmail = '', tokenExpires = 0, busy = false, cooldownUntil = 0, sent = false;
    var revision = 0, expiryTimer;
    var emailValue = function () { return emailPolicy.normalize($email.val()); };
    var show = function ($element, message, error) { $element.toggleClass('is-error', !!error).text(message); };
    var update = function () {
      var remaining = Math.max(0, Math.ceil((cooldownUntil - Date.now()) / 1000));
      $send.prop('disabled', busy || remaining > 0 || !!token).text(busy ? 'Please wait...' : remaining ? 'Resend OTP in ' + remaining + 's' : sent ? 'Resend OTP' : 'Send OTP');
      $verify.prop('disabled', busy || !!token);
      $submit.prop('disabled', busy || !token || verifiedEmail !== emailValue() || Date.now() >= tokenExpires);
    };
    var clearVerification = function () {
      revision++; token = ''; verifiedEmail = ''; tokenExpires = 0; sent = false;
      window.clearTimeout(expiryTimer);
      $otp.val(''); $section.prop('hidden', true);
      show($emailStatus, '', false); update();
    };
    var validateEmail = function () {
      var message = emailPolicy.validate(emailValue());
      $email[0].setCustomValidity(message);
      if (message) { show($emailStatus, message, true); $email[0].reportValidity(); return false; }
      return true;
    };
    $number.attr({ required: true, maxlength: 10, inputmode: 'numeric', pattern: '[6-9][0-9]{9}' });
    $form.find('[name="name"], [name="company"]').attr('required', true);
    $email.attr({ required: true, type: 'email', autocomplete: 'email' });
    $number.on('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 10); this.setCustomValidity(''); });
    $otp.on('input', function () { this.value = this.value.replace(/\D/g, '').slice(0, 6); });
    $email.on('input change', function () {
      clearVerification();
      this.setCustomValidity(this.value ? emailPolicy.validate(this.value) : '');
    });
    $form.on('reset', clearVerification);
    window.setInterval(update, 1000);
    update();

    $send.on('click', async function () {
      if (busy || Date.now() < cooldownUntil || !validateEmail()) return;
      var email = emailValue(), currentRevision = revision;
      busy = true; update(); $send.text('Sending...');
      show($emailStatus, 'Sending verification code...', false);
      try {
        var result = await callBackend(form.action, { action: 'sendOtp', email: email });
        // Keep cooldown even when the field changed during the request.
        if (result.success || result.retryAfter) cooldownUntil = Date.now() + (result.retryAfter || 60) * 1000;
        if (currentRevision !== revision || email !== emailValue()) return;
        show($emailStatus, result.message, !result.success);
        if (result.success) { sent = true; $section.prop('hidden', false); $otp.val('').trigger('focus'); }
      } catch (error) { if (currentRevision === revision) show($emailStatus, error.message, true); }
      finally { busy = false; update(); }
    });
    $verify.on('click', async function () {
      if (busy || !validateEmail()) return;
      if (!/^\d{6}$/.test($otp.val())) { show($emailStatus, 'Please enter the 6-digit verification code.', true); return; }
      var email = emailValue(), currentRevision = revision;
      busy = true; update(); $verify.text('Verifying...');
      try {
        var result = await callBackend(form.action, { action: 'verifyOtp', email: email, otp: $otp.val() });
        if (currentRevision !== revision || email !== emailValue()) return;
        show($emailStatus, result.message, !result.success);
        if (result.success && /^[a-f0-9]{64}$/.test(result.verificationToken || '') && result.expiresIn > 0) {
          token = result.verificationToken; verifiedEmail = email;
          tokenExpires = Date.now() + result.expiresIn * 1000;
          $section.prop('hidden', true); $otp.val('');
          show($emailStatus, '✓ Business email verified', false);
          expiryTimer = window.setTimeout(function () {
            clearVerification(); show($emailStatus, 'Email verification has expired. Please request a new OTP.', true);
          }, result.expiresIn * 1000);
        }
      } catch (error) { if (currentRevision === revision) show($emailStatus, error.message, true); }
      finally { busy = false; $verify.text('Verify OTP'); update(); }
    });
    $form.on('submit', async function (event) {
      event.preventDefault();
      if (busy) return;
      if (!validateEmail() || !form.reportValidity()) return;
      if (!mobileNumberPattern.test($number.val())) { show($status, 'Please enter a valid 10-digit mobile number.', true); return; }
      if (!token || verifiedEmail !== emailValue() || Date.now() >= tokenExpires) {
        clearVerification(); show($status, 'Verify your business email before requesting a demo.', true); return;
      }
      var values = {};
      new FormData(form).forEach(function (value, key) { values[key] = value; });
      values.action = 'submitLead'; values.email = emailValue(); values.verificationToken = token;
      values.page_path = window.location.pathname;
      attributionKeys.forEach(function (key) { values[key] = readAttribution(key) || landingParams.get(key) || ''; });
      var currentRevision = revision;
      busy = true; update(); $submit.text('Submitting...');
      show($status, 'Submitting...', false);
      try {
        var result = await callBackend(form.action, values);
        if (result.success) { form.reset(); cooldownUntil = 0; show($status, 'Thank you! Your demo request has been received. Our team will be in touch.', false); }
        else {
          if (result.requiresVerification && currentRevision === revision) clearVerification();
          show($status, result.message, true);
        }
      } catch (error) { show($status, error.message, true); }
      finally { busy = false; $submit.html(submitText); update(); }
    });
  });

  window.setTimeout(verifyEmbeddedForms, 2500);
  window.setTimeout(verifyEmbeddedForms, 5000);
});
