function createCookie(a,b,c){if(c){var d=new Date;d.setTime(d.getTime()+24*c*60*60*1e3);var e="; expires="+d.toGMTString()}else var e="";document.cookie=a+"="+b+e+"; path=/"}function readCookie(a){for(var b=a+"=",c=document.cookie.split(";"),d=0;d<c.length;d++){for(var e=c[d];" "==e.charAt(0);)e=e.substring(1,e.length);if(0==e.indexOf(b))return e.substring(b.length,e.length)}return null}function eraseCookie(a){createCookie(a,"",-1)}function formatCountry(a){if(!a.id||void 0===a.element)return a.text;var b=a.element.getAttribute("data-iso");return null===b?role.text:$('<div class="c-flag c-flag--'+b.toLowerCase()+'"></div> <span>'+a.text+"</span>")}function formatRole(a){if(!a.id||void 0===a.element)return a.text;var b=a.element.getAttribute("data-icon");return null===b?a.text:$('<i class="fa '+b+'"></i> <span>'+a.text+"</span>")}$(document).ready(function(){$.fn.select2&&$(".js-select__country").select2({templateResult:formatCountry,templateSelection:formatCountry})}),$(document).ready(function(){$.fn.select2&&$(".js-select__role").select2({templateResult:formatRole,templateSelection:formatRole,width:"100%"})}),$(document).ready(function(){$.fn.select2&&$(".js-select__timezone").select2()});