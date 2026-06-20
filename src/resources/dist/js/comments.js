<<<<<<< HEAD
!function(){var e=this,t=!0,i=/xyz/.test((function(){xyz}))?/\b__super__\b/:/.*/;Base=function(t){if(this==e)return Base.extend(t)},Base.extend=function(e){function n(){t&&this.init&&this.init.apply(this,arguments)}function s(e){for(var t in e)e.hasOwnProperty(t)&&(n[t]=e[t])}var r=this.prototype;t=!1;var o=new this;return t=!0,n.include=function(e){for(var t in e)if("include"==t)if(e[t]instanceof Array)for(var a=0,c=e[t].length;a<c;++a)n.include(e[t][a]);else n.include(e[t]);else if("extend"==t)if(e[t]instanceof Array)for(var a=0,c=e[t].length;a<c;++a)s(e[t][a]);else s(e[t]);else e.hasOwnProperty(t)&&(o[t]="function"==typeof e[t]&&"function"==typeof r[t]&&i.test(e[t])?function(e,t){return function(){return this.__super__=r[e],t.apply(this,arguments)}}(t,e[t]):e[t])},n.include(e),n.prototype=o,n.constructor=n,n.extend=arguments.callee,n}}(),Comments={},Comments.translations={},Comments.Base=Base.extend({addClass:function(e,t){e.classList?e.classList.add(t):e.className+=" "+t},removeClass:function(e,t){e.classList?e.classList.remove(t):e.className=e.className.replace(new RegExp("(^|\\b)"+t.split(" ").join("|")+"(\\b|$)","gi")," ")},toggleClass:function(e,t){if(e.classList)e.classList.toggle(t);else{var i=e.className.split(" "),n=i.indexOf(t);n>=0?i.splice(n,1):i.push(t),e.className=i.join(" ")}},createElement:function(e){var t=document.createElement("div");return t.innerHTML=e,t.firstElementChild},serialize:function(e){var t=new FormData(e);return t.set(Comments.csrfTokenName,Comments.csrfToken),t},serializeObject:function(e){var t=Object.keys(e).map((function(t){return encodeURIComponent(t)+"="+encodeURIComponent(e[t])}));return t.push(encodeURIComponent(Comments.csrfTokenName)+"="+encodeURIComponent(Comments.csrfToken)),t.join("&")},ajax:function(e,t){t=t||{};var i=new XMLHttpRequest;i.open(t.method||"GET",e,!0),i.setRequestHeader("X-Requested-With","XMLHttpRequest"),i.setRequestHeader("Accept","application/json"),"formData"!=t.contentType&&i.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),i.onreadystatechange=function(e){if(4===i.readyState)try{var n=JSON.parse(i.responseText);200===i.status&&t.success?n.errors?t.error(n):t.success(n):200!=i.status&&t.error&&(n.error&&(n=[[n.error]]),t.error(n))}catch(e){t.error([e])}},i.send(t.data||"")},addListener:function(e,t,i,n){e&&e.addEventListener(t,i.bind(this),n||!0)},remove:function(e){e&&e.parentNode.removeChild(e)},clearNotifications:function(e){var t=e.querySelectorAll('[data-role="notice"], [data-role="errors"]');t&&Array.prototype.forEach.call(t,(function(e,t){e.innerHTML=""}))},setNotifications:function(e,t,i){if(i&&t)if("error"===e){var n=i.errors||i;Object.keys(n).forEach((function(e){t.querySelector('[data-role="errors"]').innerHTML=n[e][0]}))}else"validation"===e?Object.keys(i).forEach((function(e){var n=t.querySelector('[name="fields['+e+']"]');if(n||(n=t.querySelector('[name="fields['+e+'][]"]')),n){const t=n.closest('[data-role="comment-field"]');if(t){const n=t.querySelector('[data-role="errors"]');n&&(n.innerHTML=i[e][0])}else n.nextElementSibling.innerHTML=i[e][0]}})):t.querySelector('[data-role="notice"]').innerHTML=i},checkCaptcha:function(e,t){if(!Comments.recaptchaEnabled)return t(e,this);grecaptcha.execute(Comments.recaptchaKey,{action:"commentForm"}).then((function(i){return e.append("g-recaptcha-response",i),t(e,this)}))},postForm:function(e,t,i){var n=e.target,s=this.serialize(n),r=n.querySelector('[type="submit"]');this.clearNotifications(n),this.addClass(r,"loading"),this.checkCaptcha(s,function(e){this.ajax(Comments.baseUrl+t,{method:"POST",contentType:"formData",data:e,success:function(e){this.removeClass(r,"loading"),e.notice&&this.setNotifications("notice",n,e.notice),e.success?i(e):this.setNotifications("validation",n,e.errors)}.bind(this),error:function(e){this.removeClass(r,"loading"),this.setNotifications("validation",n,e.errors)}.bind(this)})}.bind(this))},t:function(e){return Comments.translations.hasOwnProperty(e)?Comments.translations[e]:""},makeUniqueID:function(e="ID"){var t=(new Date).getTime(),i;return window.performance&&"function"==typeof window.performance.now&&(t+=performance.now()),e+"_"+"xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g,(function(e){var i=(t+16*Math.random())%16|0;return t=Math.floor(t/16),("x"==e?i:3&i|8).toString(16)}))},find:function(e,t){removeId=!1,null===e.getAttribute("id")&&(e.setAttribute("id",this.makeUniqueID()),removeId=!0);let i=document.querySelector("#"+e.getAttribute("id")+" > "+t);return removeId&&e.removeAttribute("id"),i},emit:function(e,t){const i="comments:"+e;document.dispatchEvent(new CustomEvent(i,{detail:t}))}}),Comments.Instance=Comments.Base.extend({comments:{},init:function(e,t){this.settings=t;var i=document.querySelector(e),n=i.querySelectorAll('[data-role="comment"]');Comments.baseUrl=t.baseUrl+"/comments/comments/",Comments.csrfTokenName=t.csrfTokenName,Comments.csrfToken=t.csrfToken,Comments.translations=t.translations,Comments.recaptchaEnabled=t.recaptchaEnabled,Comments.recaptchaKey=t.recaptchaKey,this.$commentsContainer=i.querySelector('[data-role="comments"]'),this.$baseForm=i.querySelector('[data-role="form"]'),this.$subscribeBtn=i.querySelector('[data-action="subscribe"]'),this.addListener(this.$baseForm,"submit",this.onSubmit,!1),this.addListener(this.$subscribeBtn,"click",this.subscribe);for(var s=0;s<n.length;s++){var e=n[s].getAttribute("data-id");this.comments[e]=new Comments.Comment(this,n[s])}setTimeout(function(){if(this.$baseForm){var e=this.$baseForm.querySelector('[name="'+Comments.csrfTokenName+'"]');e&&(Comments.csrfToken=e.value)}}.bind(this),2e3),this.$baseForm&&(this.commentForm=this.$baseForm.cloneNode(!0)),this.emit("init",{comments:this})},onSubmit:function(e){e.preventDefault(),this.postForm(e,"save",function(e){if(e.html){var t=this.createElement(e.html);if("asc"===this.settings.orderBy)var i=this.$commentsContainer.appendChild(t);else var i=this.$commentsContainer.insertBefore(t,this.$commentsContainer.firstChild);this.comments[e.id]=new Comments.Comment(this,t),this.$baseForm.querySelector("form").reset(),location.hash="#comment-"+e.id,this.emit("submit",{comments:this})}e.success&&(this.$baseForm.querySelector("form").reset(),this.emit("submit",{comments:this}))}.bind(this))},subscribe:function(e){e.preventDefault();var t=this.settings.element.id,i=this.settings.element.siteId,n=this.$subscribeBtn.parentNode;this.clearNotifications(n),this.toggleClass(this.$subscribeBtn,"is-subscribed"),this.ajax(Comments.baseUrl+"subscribe",{method:"POST",data:this.serializeObject({ownerId:t,siteId:i}),success:function(e){if(!e.success)throw new Error(e)}.bind(this),error:function(e){e.errors&&this.setNotifications("error",n,e.errors)}.bind(this)})}}),Comments.Comment=Comments.Base.extend({init:function(e,t){this.instance=e,this.$element=t,this.commentId=t.getAttribute("data-id"),this.siteId=t.getAttribute("data-site-id"),this.$replyContainer=this.find(t,'[data-role="wrap-content"] > [data-role="reply"]'),this.$repliesContainer=this.find(t,'[data-role="wrap-content"] > [data-role="replies"]');var i=this.find(t,'[data-role="wrap-content"] > [data-role="content"]');this.$replyBtn=i.querySelector('[data-action="reply"]'),this.$editBtn=i.querySelector('[data-action="edit"]'),this.$deleteForm=i.querySelector('[data-action="delete"]'),this.$flagForm=i.querySelector('[data-action="flag"]'),this.$upvoteForm=i.querySelector('[data-action="upvote"]'),this.$downvoteForm=i.querySelector('[data-action="downvote"]'),this.$subscribeBtn=i.querySelector('[data-action="subscribe"]'),this.replyForm=new Comments.ReplyForm(this),this.editForm=new Comments.EditForm(this),this.addListener(this.$replyBtn,"click",this.reply),this.addListener(this.$editBtn,"click",this.edit),this.addListener(this.$deleteForm,"submit",this.delete),this.addListener(this.$flagForm,"submit",this.flag),this.addListener(this.$upvoteForm,"submit",this.upvote),this.addListener(this.$downvoteForm,"submit",this.downvote),this.addListener(this.$subscribeBtn,"click",this.subscribe)},reply:function(e){e.preventDefault(),this.replyForm.isOpen?(this.$replyBtn.innerHTML=this.t("reply"),this.replyForm.closeForm(),this.instance.emit("reply-close",{comment:this})):(this.$replyBtn.innerHTML=this.t("close"),this.replyForm.openForm(),this.instance.emit("reply-open",{comment:this}))},edit:function(e){e.preventDefault(),this.editForm.isOpen?(this.$editBtn.innerHTML=this.t("edit"),this.editForm.closeForm(),this.instance.emit("edit-close",{comment:this})):(this.$editBtn.innerHTML=this.t("close"),this.editForm.openForm(),this.instance.emit("edit-open",{comment:this}))},delete:function(e){e.preventDefault(),this.clearNotifications(this.$element);var t=this.serialize(e.target),i="remove";this.instance.settings.trashAction&&(i=this.instance.settings.trashAction);var n=this.find(this.$element,'[data-role="wrap-content"] > [data-role="content"] > [data-role="body"] > [data-role="message"]');1==confirm(this.t("delete-confirm"))&&this.ajax(Comments.baseUrl+"trash",{method:"POST",contentType:"formData",data:t,success:function(e){"remove"===i?this.$element.parentNode.removeChild(this.$element):"message"===i&&n?n.innerHTML=this.instance.settings.trashActionMessage:"refresh"===i&&location.reload(),this.instance.emit("delete",{comment:this})}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},flag:function(e){e.preventDefault();var t=this.serialize(e.target);this.clearNotifications(this.$element),this.ajax(Comments.baseUrl+"flag",{method:"POST",contentType:"formData",data:t,success:function(e){this.toggleClass(this.$flagForm.parentNode,"has-flag"),e.notice&&(console.log(e.notice),this.setNotifications("notice",this.$element,e.notice)),this.instance.emit("flag",{comment:this})}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},upvote:function(e){e.preventDefault();var t=this.serialize(e.target);this.ajax(Comments.baseUrl+"vote",{method:"POST",contentType:"formData",data:t,success:function(e){this.vote(!0),this.instance.emit("upvote",{comment:this})}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},downvote:function(e){e.preventDefault();var t=this.serialize(e.target);this.ajax(Comments.baseUrl+"vote",{method:"POST",contentType:"formData",data:t,success:function(e){this.vote(!1),this.instance.emit("downvote",{comment:this})}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},vote:function(e){var t=this.$element.querySelector('[data-role="likes"]'),i=parseInt(t.textContent,10);i||(i=0),e?i++:i--,0===i&&(i=""),t.textContent=i},subscribe:function(e){e.preventDefault();var t=this.instance.settings.element.id,i=this.siteId,n=this.commentId;this.toggleClass(this.$subscribeBtn,"is-subscribed"),this.ajax(Comments.baseUrl+"subscribe",{method:"POST",data:this.serializeObject({ownerId:t,siteId:i,commentId:n}),success:function(e){if(!e.success)throw new Error(e);this.instance.emit("subscribe",{comment:this})}.bind(this),error:function(e){e.errors}.bind(this)})}}),Comments.ReplyForm=Comments.Base.extend({isOpen:!1,init:function(e){this.comment=e,this.instance=e.instance,this.$element=e.$element,this.$container=e.$replyContainer,this.$repliesContainer=e.$repliesContainer},setFormHtml:function(e){var t=this.instance.commentForm.cloneNode(!0);this.clearNotifications(t),t.querySelector("form").reset(),null!==t.getAttribute("id")&&t.setAttribute("id",this.makeUniqueID(t.getAttribute("id"))),(t.querySelector('input[name="newParentId"]')||{}).value=this.comment.commentId,this.$container.innerHTML=t.outerHTML},openForm:function(e){this.setFormHtml(e),this.isOpen=!0,this.$form=this.$container.querySelector('[role="form"]'),this.$form&&this.addListener(this.$form,"submit",this.onSubmit,!1)},closeForm:function(){this.$container.innerHTML="",this.isOpen=!1},onSubmit:function(e){e.preventDefault(),this.postForm(e,"save",function(e){if(e.html){var t=this.createElement(e.html);this.remove(this.$container.firstChild),this.$repliesContainer.insertBefore(t,this.$repliesContainer.firstChild),this.instance.comments[e.id]=new Comments.Comment(this.instance,t),this.comment.$replyBtn.innerHTML=this.t("reply"),this.isOpen=!1,this.instance.emit("reply-submit",{reply:this})}e.success&&(this.$form.reset(),this.instance.emit("reply-submit",{reply:this}))}.bind(this))}}),Comments.EditForm=Comments.Base.extend({isOpen:!1,init:function(e){this.comment=e,this.instance=e.instance,this.$element=e.$element,this.$container=e.$replyContainer,this.$comment=this.$element.querySelector('[data-role="message"]'),this.commentText=this.$comment.innerHTML.replace(/<[^>]+>/g,"").trim()},setFormHtml:function(){var e=this.instance.commentForm.cloneNode(!0);this.clearNotifications(e),this.remove(e.querySelector('[name="fields[name]"]')),this.remove(e.querySelector('[name="fields[email]"]')),this.remove(e.querySelector(".cc-i-figure")),null!==e.getAttribute("id")&&e.setAttribute("id",this.makeUniqueID(e.getAttribute("id"))),e.querySelector('[name="fields[comment]"]').innerHTML=this.commentText,e.querySelector('[type="submit"]').innerHTML=this.t("save"),(e.querySelector('input[name="commentId"]')||{}).value=this.comment.commentId,this.$comment.innerHTML=e.outerHTML},openForm:function(){this.setFormHtml(),this.isOpen=!0,this.addListener(this.$comment.querySelector('[role="form"]'),"submit",this.onSubmit,!1)},closeForm:function(){var e;this.$element.querySelector('[data-role="message"]').innerHTML="<p>"+this.commentText.replace(/\n/g,"<br>")+"</p>",this.isOpen=!1},onSubmit:function(e){e.preventDefault(),this.postForm(e,"save",function(e){var t=this.$element.querySelector('[data-role="message"]'),i=this.$element.querySelector('[name="fields[comment]"]').value;t.innerHTML="<p>"+i.replace(/\n/g,"<br>\n")+"</p>",this.comment.editForm=new Comments.EditForm(this.comment),this.comment.$editBtn.innerHTML=this.t("edit"),this.isOpen=!1,this.instance.emit("edit-submit",{edit:this})}.bind(this))}});
//# sourceMappingURL=comments.js.map
=======

// OO - Class - Copyright TJ Holowaychuk <tj@vision-media.ca> (MIT Licensed)
// Based on http://ejohn.org/blog/simple-javascript-inheritance/
// which is based on implementations by Prototype / base2

;(function(){
  var global = this, initialize = true
  var referencesSuper = /xyz/.test(function(){ xyz }) ? /\b__super__\b/ : /.*/

  /**
   * Shortcut for Class.extend()
   *
   * @param  {hash} props
   * @return {function}
   * @api public
   */

  Base = function(props){
    if (this == global)
      return Base.extend(props)  
  }
  
  // --- Version
  
  // Base.version = '1.2.0'
  
  /**
   * Create a new class.
   *
   *   User = Class({
   *     init: function(name){
   *       this.name = name
   *     }
   *   })
   *
   * Classes may be subclassed using the .extend() method, and
   * the associated superclass method via this.__super__().
   *
   *   Admin = User.extend({
   *     init: function(name, password) {
   *       this.__super__(name)
   *       // or this.__super__.apply(this, arguments)
   *       this.password = password
   *     }
   *   })
   *
   * @param  {hash} props
   * @return {function}
   * @api public
   */
  
  Base.extend = function(props) {
    var __super__ = this.prototype
    
    initialize = false
    var prototype = new this
    initialize = true

    function Base() {
      if (initialize && this.init)
        this.init.apply(this, arguments)
    }
    
    function extend(props) {
      for (var key in props)
        if (props.hasOwnProperty(key))
          Base[key] = props[key]
    }
    
    Base.include = function(props) {
      for (var name in props)
        if (name == 'include')
          if (props[name] instanceof Array)
            for (var i = 0, len = props[name].length; i < len; ++i)
              Base.include(props[name][i])
          else
            Base.include(props[name])
        else if (name == 'extend')
          if (props[name] instanceof Array)
            for (var i = 0, len = props[name].length; i < len; ++i)
              extend(props[name][i])
          else
            extend(props[name])
        else if (props.hasOwnProperty(name))
          prototype[name] = 
            typeof props[name] == 'function' &&
            typeof __super__[name] == 'function' &&
            referencesSuper.test(props[name]) ?
              (function(name, fn){
                return function() {
                  this.__super__ = __super__[name]
                  return fn.apply(this, arguments)
                }
              })(name, props[name])
            : props[name]
    }
    
    Base.include(props)
    Base.prototype = prototype
    Base.constructor = Base
    Base.extend = arguments.callee
    
    return Base
  }
})()// ==========================================================================

// Comments Plugin for Craft CMS
// Author: Verbb - https://verbb.io/

// ==========================================================================

// @codekit-prepend "_base.js"

Comments = {};

Comments.translations = {};

Comments.Base = Base.extend({
    addClass: function(el, className) {
        if (el.classList) {
            el.classList.add(className);
        } else {
            el.className += ' ' + className;
        }
    },

    removeClass: function(el, className) {
        if (el.classList) {
            el.classList.remove(className);
        } else {
            el.className = el.className.replace(new RegExp('(^|\\b)' + className.split(' ').join('|') + '(\\b|$)', 'gi'), ' ');
        }
    },

    toggleClass: function(el, className) {
        if (el.classList) {
            el.classList.toggle(className);
        } else {
            var classes = el.className.split(' ');
            var existingIndex = classes.indexOf(className);

            if (existingIndex >= 0) {
                classes.splice(existingIndex, 1);
            } else {
                classes.push(className);
            }

            el.className = classes.join(' ');
        }
    },

    createElement: function(html) {
        var el = document.createElement('div');
        el.innerHTML =  html;
        return el.firstChild;
    },

    serialize: function(form) {
        var formData = new FormData(form);

        // Set CSRF to each request, just in case
        formData.set(Comments.csrfTokenName, Comments.csrfToken);

        return formData;
    },

    serializeObject: function(json) {
        var qs = Object.keys(json).map(function(key) { 
            return encodeURIComponent(key) + '=' + encodeURIComponent(json[key]);
        });

        // Add CSRF to each request
        qs.push(encodeURIComponent(Comments.csrfTokenName) + "=" + encodeURIComponent(Comments.csrfToken));

        return qs.join('&');
    },

    ajax: function(url, settings) {
        settings = settings || {};

        var xhr = new XMLHttpRequest();
        xhr.open(settings.method || 'GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        if (settings.contentType != 'formData') {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        xhr.onreadystatechange = function (state) {
            if (xhr.readyState === 4) {
                try {
                    var response = JSON.parse(xhr.responseText);

                    if (xhr.status === 200 && settings.success) {
                        if (response.errors) {
                            settings.error(response);
                        } else {
                            settings.success(response);
                        }
                    } else if (xhr.status != 200 && settings.error) {
                        if (response.error) {
                            response = [[response.error]];
                        }

                        settings.error(response);
                    }
                } catch(e) {
                    settings.error([e]);
                }
            }
        };

        xhr.send(settings.data || '');
    },

    addListener: function($element, event, func, useCapture) {
        if ($element) {
            $element.addEventListener(event, func.bind(this), useCapture || true);
        }
    },

    remove: function($element) {
        if ($element) {
            $element.parentNode.removeChild($element);
        }
    },

    clearNotifications: function($element) {
        var $elements = $element.querySelectorAll('[data-role="notice"], [data-role="errors"]');

        if ($elements) {
            Array.prototype.forEach.call($elements, function(el, i) {
                el.innerHTML = '';
            });
        }
    },

    setNotifications: function(type, $element, content) {
        if (content && $element) {
            if (type === 'error') {
                var errors = content.errors || content;

                Object.keys(errors).forEach(function(key) {
                    $element.querySelector('[data-role="errors"]').innerHTML = errors[key][0];
                });
            } else if (type === 'validation') {
                Object.keys(content).forEach(function(key) {
                    var $field = $element.querySelector('[name="fields[' + key + ']"]');

                    if (!$field) {
                        $field = $element.querySelector('[name="fields[' + key + '][]"]');
                    }

                    if ($field) {
                        $field.nextElementSibling.innerHTML = content[key][0];
                    }
                });
            } else {
                $element.querySelector('[data-role="notice"]').innerHTML = content;
            }
        }
    },

    checkCaptcha: function(formData, callback) {
        // Only trigger if reCAPTCHA enabled
        if (!Comments.recaptchaEnabled) {
            return callback(formData, this);
        }

        // Check for reCAPTCHA
        grecaptcha.execute(Comments.recaptchaKey, { action: 'commentForm' }).then(function(token) {
            // Append value to the form and proceed
            formData.append('g-recaptcha-response', token);

            return callback(formData, this);
        });
    },

    postForm: function(e, url, callback) {
        var $form = e.target;
        var data = this.serialize($form);
        var $btn = $form.querySelector('[type="submit"]');

        this.clearNotifications($form);
        this.addClass($btn, 'loading');

        this.checkCaptcha(data, function(data) {
            this.ajax(Comments.baseUrl + url, {
                method: 'POST',
                contentType: 'formData',
                data: data,
                success: function(xhr) {
                    this.removeClass($btn, 'loading');

                    if (xhr.notice) {
                        this.setNotifications('notice', $form, xhr.notice);
                    }

                    if (xhr.success) {
                        callback(xhr);
                    } else {
                        this.setNotifications('validation', $form, xhr.errors);
                    }
                }.bind(this),
                error: function(xhr) {
                    this.removeClass($btn, 'loading');
                    this.setNotifications('validation', $form, xhr.errors);
                }.bind(this)
            });
        }.bind(this));
    },

    t: function(key) {
        return (Comments.translations.hasOwnProperty(key)) ? Comments.translations[key] : '';
    },
	
	makeUniqueID: function(prefix = 'ID') {
		var d = new Date().getTime();

		if (window.performance && typeof window.performance.now === 'function') {
			d += performance.now();
		}

		var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
			var r = (d + Math.random() * 16) % 16 | 0;
			d = Math.floor(d/16);
			return (c == 'x' ? r : (r&0x3|0x8)).toString(16);
		});

		return prefix + '_' + uuid;
	},

    find: function(node, selector) {
        removeId = false;

        if (node.getAttribute('id') === null) {
            node.setAttribute('id', this.makeUniqueID());
            removeId = true;
        }

        let result = document.querySelector('#' + node.getAttribute('id') + ' > ' + selector);

        if (removeId) {
            node.removeAttribute('id');
        }

        return result;
    },
});

Comments.Instance = Comments.Base.extend({
    comments: {},

    init: function(id, settings) {
        this.settings = settings;

        var $container = document.querySelector(id);
        var $comments = $container.querySelectorAll('[data-role="comment"]');

        // Setup some global variables
        Comments.baseUrl = settings.baseUrl + '/comments/comments/';
        Comments.csrfTokenName = settings.csrfTokenName;
        Comments.csrfToken = settings.csrfToken;
        Comments.translations = settings.translations;
        Comments.recaptchaEnabled = settings.recaptchaEnabled;
        Comments.recaptchaKey = settings.recaptchaKey;

        this.$container = $container;
        this.$commentsContainer = $container.querySelector('[data-role="comments"]');
        this.$baseForm = $container.querySelector('[data-role="form"]');

        // Actions
        this.$subscribeBtn = $container.querySelector('[data-action="subscribe"]');

        this.addListener(this.$baseForm, 'submit', this.onSubmit, false);
        this.addListener(this.$subscribeBtn, 'click', this.subscribe);

        // GIF picker. Bound once on the container and event-delegated, so it covers the base
        // form as well as any reply forms cloned into the DOM later on.
        if (this.settings.giphyEnabled) {
            this.$container.addEventListener('click', this.onGiphyClick.bind(this), false);
            this.$container.addEventListener('input', this.onGiphyInput.bind(this), false);
            this.$container.addEventListener('keydown', this.onGiphyKeydown.bind(this), false);
        }

        // Create classes for each comment item
        for (var i = 0; i < $comments.length; i++) {
            var id = $comments[i].getAttribute('data-id');

            this.comments[id] = new Comments.Comment(this, $comments[i]);
        }

        // Update the CSRF token from the form. This plays nicely with Blitz.
        setTimeout(function() {
            if (this.$baseForm) {
                var $csrfTokenInput = this.$baseForm.querySelector('[name="' + Comments.csrfTokenName + '"]');

                if ($csrfTokenInput) {
                    Comments.csrfToken = $csrfTokenInput.value;
                }
            }
        }.bind(this), 2000);
    },

    onSubmit: function(e) {
        e.preventDefault();

        this.postForm(e, 'save', function(xhr) {

            if (xhr.html) {
                var $html = this.createElement(xhr.html);

                // Insert at top of bottom of list depending on plugin settings
                if (this.settings.orderBy === 'asc') {
                    var $newComment = this.$commentsContainer.appendChild($html);
                } else {
                    var $newComment = this.$commentsContainer.insertBefore($html, this.$commentsContainer.firstChild);
                }

                this.comments[xhr.id] = new Comments.Comment(this, $html);

                this.$baseForm.querySelector('form').reset();
                this.giphyReset(this.$baseForm);

                // Scroll to the new comment
                location.hash = '#comment-' + xhr.id;
            }

            // If a comment was successfully submitted but under review
            if (xhr.success) {
                this.$baseForm.querySelector('form').reset();
            }

        }.bind(this));
    },

    subscribe: function(e) {
        e.preventDefault();

        var ownerId = this.settings.element.id;
        var siteId = this.settings.element.siteId;

        var $commentHeader = this.$subscribeBtn.parentNode;

        this.clearNotifications($commentHeader);

        this.toggleClass(this.$subscribeBtn, 'is-subscribed');

        this.ajax(Comments.baseUrl + 'subscribe', {
            method: 'POST',
            data: this.serializeObject({ ownerId: ownerId, siteId: siteId }),
            success: function(xhr) {
                if (!xhr.success) {
                    throw new Error(xhr);
                }
            }.bind(this),
            error: function(response) {
                if (response.errors) {
                    this.setNotifications('error', $commentHeader, response.errors);
                }
            }.bind(this),
        });
    },

    //
    // GIF picker (GIPHY)
    //

    onGiphyClick: function(e) {
        var $toggle = e.target.closest('[data-action="giphy-toggle"]');

        if ($toggle) {
            e.preventDefault();
            return this.giphyToggle($toggle);
        }

        var $result = e.target.closest('[data-role="giphy-result"]');

        if ($result) {
            e.preventDefault();
            return this.giphySelect($result);
        }

        var $remove = e.target.closest('[data-action="giphy-remove"]');

        if ($remove) {
            e.preventDefault();
            return this.giphyRemove($remove);
        }
    },

    onGiphyInput: function(e) {
        if (!e.target.closest) {
            return;
        }

        var $search = e.target.closest('[data-role="giphy-search"]');

        if (!$search) {
            return;
        }

        var $giphy = $search.closest('[data-role="giphy"]');

        clearTimeout(this._giphyTimer);

        this._giphyTimer = setTimeout(function() {
            this.giphySearch($giphy);
        }.bind(this), 350);
    },

    onGiphyKeydown: function(e) {
        if (e.key !== 'Enter' && e.keyCode !== 13) {
            return;
        }

        if (!e.target.closest) {
            return;
        }

        var $search = e.target.closest('[data-role="giphy-search"]');

        if (!$search) {
            return;
        }

        // Don't let Enter in the search field submit the comment form
        e.preventDefault();

        clearTimeout(this._giphyTimer);
        this.giphySearch($search.closest('[data-role="giphy"]'));
    },

    giphyToggle: function($toggle) {
        var $giphy = $toggle.closest('[data-role="giphy"]');
        var $panel = $giphy.querySelector('[data-role="giphy-panel"]');

        if ($panel.hasAttribute('hidden')) {
            $panel.removeAttribute('hidden');
            $toggle.setAttribute('aria-expanded', 'true');

            var $search = $giphy.querySelector('[data-role="giphy-search"]');

            if ($search) {
                $search.focus();
            }

            // Show trending GIFs straight away the first time it's opened
            var $results = $giphy.querySelector('[data-role="giphy-results"]');

            if ($results && !$results.children.length) {
                this.giphySearch($giphy);
            }
        } else {
            $panel.setAttribute('hidden', '');
            $toggle.setAttribute('aria-expanded', 'false');
        }
    },

    giphySearch: function($giphy) {
        var $search = $giphy.querySelector('[data-role="giphy-search"]');
        var $results = $giphy.querySelector('[data-role="giphy-results"]');

        this.addClass($giphy, 'is-loading');

        this.ajax(Comments.baseUrl + 'giphy-search', {
            method: 'POST',
            data: this.serializeObject({ q: $search ? $search.value : '' }),
            success: function(xhr) {
                this.removeClass($giphy, 'is-loading');
                this.giphyRenderResults($results, (xhr && xhr.results) || []);
            }.bind(this),
            error: function() {
                this.removeClass($giphy, 'is-loading');
                this.giphyRenderResults($results, []);
            }.bind(this),
        });
    },

    giphyRenderResults: function($results, results) {
        if (!$results) {
            return;
        }

        $results.innerHTML = '';

        if (!results.length) {
            var $empty = document.createElement('div');
            $empty.className = 'cc-giphy-empty';
            $empty.textContent = this.t('giphy-empty');
            $results.appendChild($empty);

            return;
        }

        // Build the grid with DOM nodes (not innerHTML), so result data is never
        // interpreted as markup
        results.forEach(function(item) {
            var $btn = document.createElement('button');
            $btn.type = 'button';
            $btn.className = 'cc-giphy-result';
            $btn.setAttribute('data-role', 'giphy-result');
            $btn.setAttribute('data-url', item.url);

            var $img = document.createElement('img');
            $img.src = item.preview;
            $img.alt = item.title || '';
            $img.loading = 'lazy';

            $btn.appendChild($img);
            $results.appendChild($btn);
        });
    },

    giphySelect: function($result) {
        var $giphy = $result.closest('[data-role="giphy"]');
        var url = $result.getAttribute('data-url');
        var $input = $giphy.querySelector('input[name="gifUrl"]');

        if ($input) {
            $input.value = url;
        }

        this.giphyShowPreview($giphy, url);

        // Collapse the picker once a GIF is chosen
        var $panel = $giphy.querySelector('[data-role="giphy-panel"]');
        var $toggle = $giphy.querySelector('[data-action="giphy-toggle"]');

        if ($panel) {
            $panel.setAttribute('hidden', '');
        }

        if ($toggle) {
            $toggle.setAttribute('aria-expanded', 'false');
        }
    },

    giphyShowPreview: function($giphy, url) {
        var $preview = $giphy.querySelector('[data-role="giphy-preview"]');

        if (!$preview) {
            return;
        }

        $preview.innerHTML = '';

        var $img = document.createElement('img');
        $img.src = url;
        $img.alt = '';

        var $remove = document.createElement('button');
        $remove.type = 'button';
        $remove.className = 'cc-giphy-remove';
        $remove.setAttribute('data-action', 'giphy-remove');
        $remove.setAttribute('aria-label', this.t('giphy-remove'));
        $remove.innerHTML = '&times;';

        $preview.appendChild($img);
        $preview.appendChild($remove);

        this.addClass($giphy, 'has-gif');
    },

    giphyRemove: function($remove) {
        var $giphy = $remove.closest('[data-role="giphy"]');

        this.giphyReset($giphy);
    },

    giphyReset: function($scope) {
        if (!$scope) {
            return;
        }

        var $giphy = $scope.matches && $scope.matches('[data-role="giphy"]') ? $scope : $scope.querySelector('[data-role="giphy"]');

        if (!$giphy) {
            return;
        }

        var $input = $giphy.querySelector('input[name="gifUrl"]');

        if ($input) {
            $input.value = '';
        }

        var $preview = $giphy.querySelector('[data-role="giphy-preview"]');

        if ($preview) {
            $preview.innerHTML = '';
        }

        var $panel = $giphy.querySelector('[data-role="giphy-panel"]');

        if ($panel) {
            $panel.setAttribute('hidden', '');
        }

        this.removeClass($giphy, 'has-gif');
    },
});

Comments.Comment = Comments.Base.extend({
    init: function(instance, $element) {
        this.instance = instance;
        this.$element = $element;
        this.commentId = $element.getAttribute('data-id');
        this.siteId = $element.getAttribute('data-site-id');

        this.$replyContainer = this.find($element, '[data-role="wrap-content"] > [data-role="reply"]');
        this.$repliesContainer = this.find($element, '[data-role="wrap-content"] > [data-role="replies"]');

        // Make sure we restrict event-binding to the immediate container of this comment
        // Otherwise, we risk binding events multiple times on reply comments, nested within this comment
        var $contentContainer = this.find($element, '[data-role="wrap-content"] > [data-role="content"]');

        // Actions
        this.$replyBtn = $contentContainer.querySelector('[data-action="reply"]');

        this.$editBtn = $contentContainer.querySelector('[data-action="edit"]');
        this.$deleteForm = $contentContainer.querySelector('[data-action="delete"]');
        this.$flagForm = $contentContainer.querySelector('[data-action="flag"]');
        
        this.$upvoteForm = $contentContainer.querySelector('[data-action="upvote"]');
        this.$downvoteForm = $contentContainer.querySelector('[data-action="downvote"]');

        this.$subscribeBtn = $contentContainer.querySelector('[data-action="subscribe"]');

        // Additional classes
        this.replyForm = new Comments.ReplyForm(this);
        this.editForm = new Comments.EditForm(this);

        // Add event listeners
        this.addListener(this.$replyBtn, 'click', this.reply);
        
        this.addListener(this.$editBtn, 'click', this.edit);
        this.addListener(this.$deleteForm, 'submit', this.delete);
        this.addListener(this.$flagForm, 'submit', this.flag);

        this.addListener(this.$upvoteForm, 'submit', this.upvote);
        this.addListener(this.$downvoteForm, 'submit', this.downvote);

        this.addListener(this.$subscribeBtn, 'click', this.subscribe);
    },

    reply: function(e) {
        e.preventDefault();

        if (this.replyForm.isOpen) {
            this.$replyBtn.innerHTML = this.t('reply');
            this.replyForm.closeForm();
        } else {
            this.$replyBtn.innerHTML = this.t('close');
            this.replyForm.openForm();
        }
    },

    edit: function(e) {
        e.preventDefault();

        if (this.editForm.isOpen) {
            this.$editBtn.innerHTML = this.t('edit');
            this.editForm.closeForm();
        } else {
            this.$editBtn.innerHTML = this.t('close');
            this.editForm.openForm();
        }
    },

    delete: function(e) {
        e.preventDefault();

        this.clearNotifications(this.$element);

        var data = this.serialize(e.target);

        var trashAction = 'remove';

        if (this.instance.settings.trashAction) {
            trashAction = this.instance.settings.trashAction;
        }

        var $message = this.find(this.$element, '[data-role="wrap-content"] > [data-role="content"] > [data-role="body"] > [data-role="message"]');

        if (confirm(this.t('delete-confirm')) == true) {
            this.ajax(Comments.baseUrl + 'trash', {
                method: 'POST',
                contentType: 'formData',
                data: data,
                success: function(xhr) {
                    if (trashAction === 'remove') {
                        this.$element.parentNode.removeChild(this.$element);
                    } else if (trashAction === 'message' && $message) {
                        $message.innerHTML = this.instance.settings.trashActionMessage;
                    } else if (trashAction === 'refresh') {
                        location.reload();
                    }
                }.bind(this),
                error: function(errors) {
                    this.setNotifications('error', this.$element, errors);
                }.bind(this),
            });
        }
    },

    flag: function(e) {
        e.preventDefault();

        var data = this.serialize(e.target);

        this.clearNotifications(this.$element);

        this.ajax(Comments.baseUrl + 'flag', {
            method: 'POST',
            contentType: 'formData',
            data: data,
            success: function(xhr) {
                this.toggleClass(this.$flagForm.parentNode, 'has-flag');

                if (xhr.notice) {
                    console.log(xhr.notice)
                    this.setNotifications('notice', this.$element, xhr.notice);
                }
            }.bind(this),
            error: function(errors) {
                this.setNotifications('error', this.$element, errors);
            }.bind(this)
        });
    },

    upvote: function(e) {
        e.preventDefault();

        var data = this.serialize(e.target);

        this.ajax(Comments.baseUrl + 'vote', {
            method: 'POST',
            contentType: 'formData',
            data: data,
            success: function(xhr) {
                this.vote(true);
            }.bind(this),
            error: function(errors) {
                this.setNotifications('error', this.$element, errors);
            }.bind(this),
        });
    },

    downvote: function(e) {
        e.preventDefault();

        var data = this.serialize(e.target);

        this.ajax(Comments.baseUrl + 'vote', {
            method: 'POST',
            contentType: 'formData',
            data: data,
            success: function(xhr) {
                this.vote(false);
            }.bind(this),
            error: function(errors) {
                this.setNotifications('error', this.$element, errors);
            }.bind(this),
        });
    },

    vote: function(up) {
        var $like = this.$element.querySelector('[data-role="likes"]');
        var count = parseInt($like.textContent, 10);
        
        if (!count) {
            count = 0;
        }

        if (up) {
            count++;
        } else {
            count--;
        }

        if (count === 0) {
            count = '';
        }
        
        $like.textContent = count;
    },

    subscribe: function(e) {
        e.preventDefault();

        var ownerId = this.instance.settings.element.id;
        var siteId = this.siteId;
        var commentId = this.commentId;

        this.toggleClass(this.$subscribeBtn, 'is-subscribed');

        this.ajax(Comments.baseUrl + 'subscribe', {
            method: 'POST',
            data: this.serializeObject({ ownerId: ownerId, siteId: siteId, commentId: commentId }),
            success: function(xhr) {
                if (!xhr.success) {
                    throw new Error(xhr);
                }
            }.bind(this),
            error: function(response) {
                if (response.errors) {
                }
            }.bind(this),   
        });
    },
});


Comments.ReplyForm = Comments.Base.extend({
    isOpen: false,

    init: function(comment) {
        this.comment = comment;
        this.instance = comment.instance;
        this.$element = comment.$element;
        this.$container = comment.$replyContainer;
        this.$repliesContainer = comment.$repliesContainer;
    },

    setFormHtml: function(comment) {
        var form = this.instance.$baseForm.cloneNode(true);

        // Clear errors and info
        this.clearNotifications(form);

        // Clear all inputs
        form.querySelector('form').reset();
		
		// make a new ID if we already have an ID
		if (form.getAttribute('id') !== null) {
            form.setAttribute('id', this.makeUniqueID(form.getAttribute('id')));
        }

        // Set the value to be the id of comment we're replying to
        (form.querySelector('input[name="newParentId"]') || {}).value = this.comment.commentId;

        // Don't carry over any GIF the user may have picked on the base form
        var $giphy = form.querySelector('[data-role="giphy"]');

        if ($giphy) {
            this.removeClass($giphy, 'has-gif');
            (form.querySelector('input[name="gifUrl"]') || {}).value = '';

            var $preview = $giphy.querySelector('[data-role="giphy-preview"]');
            if ($preview) { $preview.innerHTML = ''; }

            var $results = $giphy.querySelector('[data-role="giphy-results"]');
            if ($results) { $results.innerHTML = ''; }

            var $panel = $giphy.querySelector('[data-role="giphy-panel"]');
            if ($panel) { $panel.setAttribute('hidden', ''); }
        }

        this.$container.innerHTML = form.outerHTML;
    },

    openForm: function(comment) {
        this.setFormHtml(comment);

        this.isOpen = true;

        this.$form = this.$container.querySelector('[role="form"]');

        if (this.$form) {
            this.addListener(this.$form, 'submit', this.onSubmit, false);
        }
    },

    closeForm: function() {
        this.$container.innerHTML = '';

        this.isOpen = false;
    },

    onSubmit: function(e) {
        e.preventDefault();

        this.postForm(e, 'save', function(xhr) {
            if (xhr.html) {
                var $newComment = this.createElement(xhr.html);

                // Remove the form (empty the container)
                this.remove(this.$container.firstChild);

                // Prepend it to the original comment
                this.$repliesContainer.insertBefore($newComment, this.$repliesContainer.firstChild);

                this.instance.comments[xhr.id] = new Comments.Comment(this.instance, $newComment);

                this.comment.$replyBtn.innerHTML = this.t('reply')

                this.isOpen = false;
            }

            // If a comment was successfully submitted but under review
            if (xhr.success) {
                this.$form.reset();
            }
        }.bind(this));
    },
});


Comments.EditForm = Comments.Base.extend({
    isOpen: false,

    init: function(comment) {
        this.comment = comment;
        this.instance = comment.instance;
        this.$element = comment.$element;
        this.$container = comment.$replyContainer;

        this.$comment = this.$element.querySelector('[data-role="message"]');

        // Keep any attached GIF aside - editing only touches the comment text, and we
        // don't want to lose the GIF visually or have it stripped from the stored comment
        var $gif = this.$comment.querySelector('.cc-i-gif');
        this.gifHtml = $gif ? $gif.outerHTML : '';

        this.commentText = this.$comment.innerHTML.replace(/<[^>]+>/g, '').trim();
    },

    setFormHtml: function() {
        var form = this.instance.$baseForm.cloneNode(true);

        // Clear errors and info
        this.clearNotifications(form);

        // Remove some stuff
        this.remove(form.querySelector('[name="fields[name]"]'));
        this.remove(form.querySelector('[name="fields[email]"]'));
        this.remove(form.querySelector('.cc-i-figure'));

        // Editing is text-only - the existing GIF is preserved server-side untouched
        this.remove(form.querySelector('[data-role="giphy"]'));
		
		// make a new ID if we already have an ID
		if (form.getAttribute('id') !== null) {
            form.setAttribute('id', this.makeUniqueID(form.getAttribute('id')));
        }

        // Clear and update
        form.querySelector('[name="fields[comment]"]').innerHTML = this.commentText;
        form.querySelector('[type="submit"]').innerHTML = this.t('save');

        // Set the value to be the id of comment we're replying to
        (form.querySelector('input[name="commentId"]') || {}).value = this.comment.commentId;

        this.$comment.innerHTML = form.outerHTML;
    },

    openForm: function() {
        this.setFormHtml();

        this.isOpen = true;

        this.addListener(this.$comment.querySelector('[role="form"]'), 'submit', this.onSubmit, false);
    },

    closeForm: function() {
        var $comment = this.$element.querySelector('[data-role="message"]');

        var html = this.commentText ? '<p>' + this.commentText.replace(/\n/g, '<br>') + '</p>' : '';
        $comment.innerHTML = html + (this.gifHtml || '');

        this.isOpen = false;
    },

    onSubmit: function(e) {
        e.preventDefault();

        this.postForm(e, 'save', function(xhr) {
            var $comment = this.$element.querySelector('[data-role="message"]');
            var commentText = this.$element.querySelector('[name="fields[comment]"]').value;

            var html = commentText ? '<p>' + commentText.replace(/\n/g, '<br>\n') + '</p>' : '';
            $comment.innerHTML = html + (this.gifHtml || '');

            this.comment.editForm = new Comments.EditForm(this.comment);

            this.comment.$editBtn.innerHTML = this.t('edit');

            this.isOpen = false;
        }.bind(this));
    },
});

>>>>>>> origin/craft-4
