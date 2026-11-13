/* Mirror this policy in google-apps-script/Code.gs; tested for parity. */
(function (root) {
  'use strict';
  var blocked = ('gmail.com googlemail.com yahoo.com yahoo.in yahoo.co.in yahoo.co.uk ymail.com rocketmail.com ' +
    'hotmail.com hotmail.co.uk hotmail.co.in outlook.com outlook.in live.com live.in msn.com ' +
    'rediffmail.com rediff.com icloud.com me.com mac.com aol.com proton.me protonmail.com pm.me ' +
    'gmx.com gmx.net gmx.de mail.com mail.ru zoho.com zohomail.com yandex.com yandex.ru ' +
    'tutanota.com tuta.io fastmail.com inbox.com hushmail.com indiatimes.com sify.com vsnl.net bsnl.in airtelmail.in ' +
    'mailinator.com yopmail.com 10minutemail.com guerrillamail.com temp-mail.org tempmail.com throwawaymail.com ' +
    'sharklasers.com trashmail.com getnada.com dispostable.com maildrop.cc mailnesia.com fakeinbox.com spamgourmet.com discard.email').split(' ');
  var typos = 'gmaile.com gmial.com gmaiil.com gmail.co yahooo.com outlok.com hotmial.com'.split(' ');
  // Only long, distinctive provider names and consumer suffixes get fuzzy matching.
  // Never fuzzy-match short names (mail, me, live, zoho, gmx) or company subdomains.
  var providers = 'gmail googlemail yahoo hotmail outlook icloud protonmail rediffmail mailinator guerrillamail'.split(' ');
  function oneEdit(a, b) {
    if (a === b) return true;
    if (Math.abs(a.length - b.length) > 1) return false;
    if (a.length === b.length) {
      var differences = [];
      for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) differences.push(i);
      return differences.length === 1 || (differences.length === 2 && differences[1] === differences[0] + 1 &&
        a[differences[0]] === b[differences[1]] && a[differences[1]] === b[differences[0]]);
    }
    var shorter = a.length < b.length ? a : b;
    var longer = a.length < b.length ? b : a;
    for (var j = 0; j < longer.length; j++) if (longer.slice(0, j) + longer.slice(j + 1) === shorter) return true;
    return false;
  }
  function normalize(value) { return String(value || '').trim().toLowerCase(); }
  function validate(value) {
    var email = normalize(value);
    if (email.length > 254 || !/^[a-z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-z0-9.-]+\.[a-z]{2,63}$/.test(email)) return 'Please enter a valid email address.';
    var parts = email.split('@'), local = parts[0], domain = parts[1], labels = domain.split('.');
    if (local.length > 64 || local[0] === '.' || local.slice(-1) === '.' || local.indexOf('..') !== -1 ||
      labels.some(function (label) { return !/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/.test(label); })) return 'Please enter a valid email address.';
    var rejected = blocked.some(function (provider) { return domain === provider || domain.slice(-(provider.length + 1)) === '.' + provider; }) || typos.indexOf(domain) !== -1;
    var suffix = labels.slice(1).join('.');
    if (/^(com|co|net|org|in|co\.in|co\.uk|me)$/.test(suffix)) {
      rejected = rejected || providers.some(function (provider) { return oneEdit(labels[0], provider); });
    }
    return rejected ? 'Please use your business email address. Personal, disposable and obvious provider typo domains are not accepted.' : '';
  }
  root.SecureEdgeEmail = { normalize: normalize, validate: validate };
})(typeof module !== 'undefined' ? module.exports : window);
