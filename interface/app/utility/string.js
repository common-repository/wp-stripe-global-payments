if (!String.prototype.endsWith) {
  String.prototype.endsWith = function(search, this_len) {
    if (this_len === undefined || this_len > this.length) {
      this_len = this.length;
    }
    return this.substring(this_len - search.length, this_len) === search;
  };
}

if (!String.prototype.replaceAll) {
  String.prototype.replaceAll = function(str, newStr){
    if (Object.prototype.toString.call(str).toLowerCase() === '[object regexp]') {
      return this.replace(str, newStr);
    }

    return this.replace(new RegExp(str, 'g'), newStr);
  };
}

if (!Number.MAX_SAFE_INTEGER) {
  Number.MAX_SAFE_INTEGER = 9007199254740991; // Math.pow(2, 53) - 1;
}

/**
 *
 * @param str
 * @returns {number}
 */
function china_payments_hash_string( str ) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = ((hash << 5) - hash) + str.charCodeAt(i);
    hash = hash & hash;
  }
  return Math.round(Number.MAX_SAFE_INTEGER * Math.abs(hash) / 2147483648);
}

function china_payments_uc_first(str)  {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function china_payments_trim(s, mask) {
  while (~mask.indexOf(s[0])) {
    s = s.slice(1);
  }
  while (~mask.indexOf(s[s.length - 1])) {
    s = s.slice(0, -1);
  }
  return s;
}