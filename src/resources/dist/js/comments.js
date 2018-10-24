// OO - Class - Copyright TJ Holowaychuk <tj@vision-media.ca> (MIT Licensed)
// Based on http://ejohn.org/blog/simple-javascript-inheritance/
// which is based on implementations by Prototype / base2
!function(){var t=this,n=!0,c=/xyz/.test(function(){xyz})?/\b__super__\b/:/.*/
/**
   * Shortcut for Class.extend()
   *
   * @param  {hash} props
   * @return {function}
   * @api public
   */;Base=function(e){if(this==t)return Base.extend(e)}
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
   */,Base.extend=function(e){function s(){n&&this.init&&this.init.apply(this,arguments)}function o(e){for(var t in e)e.hasOwnProperty(t)&&(s[t]=e[t])}var r=this.prototype;n=!1;var a=new this;return n=!0,s.include=function(e){for(var t in e)if("include"==t)if(e[t]instanceof Array)for(var n=0,i=e[t].length;n<i;++n)s.include(e[t][n]);else s.include(e[t]);else if("extend"==t)if(e[t]instanceof Array)for(var n=0,i=e[t].length;n<i;++n)o(e[t][n]);else o(e[t]);else e.hasOwnProperty(t)&&(a[t]="function"==typeof e[t]&&"function"==typeof r[t]&&c.test(e[t])?function(e,t){return function(){return this.__super__=r[e],t.apply(this,arguments)}}(t,e[t]):e[t])},s.include(e),s.prototype=a,(s.constructor=s).extend=arguments.callee,s}}(),// ==========================================================================
// Comments Plugin for Craft CMS
// Author: Verbb - https://verbb.io/
// ==========================================================================
// @codekit-prepend "_base.js"
Comments={translations:{}},Comments.Base=Base.extend({addClass:function(e,t){e.classList?e.classList.add(t):e.className+=" "+t},removeClass:function(e,t){e.classList?e.classList.remove(t):e.className=e.className.replace(new RegExp("(^|\\b)"+t.split(" ").join("|")+"(\\b|$)","gi")," ")},toggleClass:function(e,t){if(e.classList)e.classList.toggle(t);else{var n=e.className.split(" "),i=n.indexOf(t);0<=i?n.splice(i,1):n.push(t),e.className=n.join(" ")}},createElement:function(e){var t=document.createElement("div");return t.innerHTML=e,t.firstChild},serialize:function(e){var n=[],t=e.querySelectorAll("input, select, textarea");return Array.prototype.forEach.call(t,function(e,t){n.push(encodeURIComponent(e.name)+"="+encodeURIComponent(e.value))}),
// Add CSRF to each request
n.push(encodeURIComponent(Comments.csrfTokenName)+"="+encodeURIComponent(Comments.csrfToken)),n.join("&")},serializeObject:function(t){var e=Object.keys(t).map(function(e){return encodeURIComponent(e)+"="+encodeURIComponent(t[e])});
// Add CSRF to each request
return e.push(encodeURIComponent(Comments.csrfTokenName)+"="+encodeURIComponent(Comments.csrfToken)),e.join("&")},ajax:function(e,n){n=n||{};var i=new XMLHttpRequest;i.open(n.method||"GET",e,!0),i.setRequestHeader("X-Requested-With","XMLHttpRequest"),i.setRequestHeader("Accept","application/json"),i.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),i.onreadystatechange=function(e){if(4===i.readyState){var t=JSON.parse(i.responseText);200===i.status&&n.success?t.errors?n.error(t):n.success(t):200!=i.status&&n.error&&(t.error&&(t=[[t.error]]),n.error(t))}},i.send(n.data||"")},addListener:function(e,t,n,i){e&&e.addEventListener(t,n.bind(this),i||!0)},remove:function(e){e&&e.parentNode.removeChild(e)},clearNotifications:function(e){var t=e.querySelectorAll('.cc-e, [data-role="notice"], [data-role="errors"]');t&&Array.prototype.forEach.call(t,function(e,t){e.innerHTML=""})},setNotifications:function(e,t,n){if(n&&t)if("error"===e){var i=n.errors||n;Object.keys(i).forEach(function(e){t.querySelector('[data-role="errors"]').innerHTML=i[e][0]})}else"validation"===e?Object.keys(n).forEach(function(e){t.querySelector('[name="fields['+e+']"]').nextElementSibling.innerHTML=n[e][0]}):t.querySelector('[data-role="notice"]').innerHTML=n},postForm:function(e,t,n){var i=e.target,s=this.serialize(i),o=i.querySelector('[type="submit"]');this.clearNotifications(i),this.addClass(o,"loading"),this.ajax(Comments.baseUrl+t,{method:"POST",data:s,success:function(e){this.removeClass(o,"loading"),e.notice&&this.setNotifications("notice",i,e.notice),e.success?n(e):this.setNotifications("validation",i,e.errors)}.bind(this),error:function(e){this.removeClass(o,"loading"),this.setNotifications("validation",i,e.errors)}.bind(this)})},t:function(e){return Comments.translations.hasOwnProperty(e)?Comments.translations[e]:""}}),Comments.Instance=Comments.Base.extend({comments:{},init:function(e,t){var n=document.querySelector(e),i=n.querySelectorAll('[data-role="comment"]');
// Setup some global variables
Comments.baseUrl=t.baseUrl+"/comments/comments/",Comments.csrfTokenName=t.csrfTokenName,Comments.csrfToken=t.csrfToken,Comments.translations=t.translations,this.$commentsContainer=n.querySelector('[data-role="comments"]'),this.$baseForm=n.querySelector('[data-role="form"]'),this.addListener(this.$baseForm,"submit",this.onSubmit,!1);
// Create classes for each comment item
for(var s=0;s<i.length;s++){var e=i[s].getAttribute("data-id");this.comments[e]=new Comments.Comment(this,i[s])}},onSubmit:function(e){e.preventDefault(),this.postForm(e,"save",function(e){if(e.html){var t=this.createElement(e.html),n=this.$commentsContainer.insertBefore(t,this.$commentsContainer.firstChild);this.comments[e.id]=new Comments.Comment(this,t),
// Clear all inputs
(this.$baseForm.querySelector('[name="fields[name]"]')||{}).value="",(this.$baseForm.querySelector('[name="fields[email]"]')||{}).value="",(this.$baseForm.querySelector('[name="fields[comment]"]')||{}).innerHTML="",
// Scroll to the new comment
location.hash="#comment-"+e.id}}.bind(this))}}),Comments.Comment=Comments.Base.extend({init:function(e,t){this.instance=e,this.$element=t,this.commentId=t.getAttribute("data-id"),this.siteId=t.getAttribute("data-site-id"),this.$replyContainer=t.querySelector('[data-role="reply"]'),this.$repliesContainer=t.querySelector('[data-role="replies"]'),
// Actions
this.$replyBtn=t.querySelector('[data-action="reply"]'),this.$editBtn=t.querySelector('[data-action="edit"]'),this.$deleteBtn=t.querySelector('[data-action="delete"]'),this.$flagBtn=t.querySelector('[data-action="flag"]'),this.$upvoteBtn=t.querySelector('[data-action="upvote"]'),this.$downvoteBtn=t.querySelector('[data-action="downvote"]'),
// Additional classes
this.replyForm=new Comments.ReplyForm(this),this.editForm=new Comments.EditForm(this),
// Add event listeners
this.addListener(this.$replyBtn,"click",this.reply),this.addListener(this.$editBtn,"click",this.edit),this.addListener(this.$deleteBtn,"click",this.delete),this.addListener(this.$flagBtn,"click",this.flag),this.addListener(this.$upvoteBtn,"click",this.upvote),this.addListener(this.$downvoteBtn,"click",this.downvote)},reply:function(e){e.preventDefault(),this.replyForm.isOpen?(this.$replyBtn.innerHTML=this.t("reply"),this.replyForm.closeForm()):(this.$replyBtn.innerHTML=this.t("close"),this.replyForm.openForm())},edit:function(e){e.preventDefault(),this.editForm.isOpen?(this.$editBtn.innerHTML=this.t("edit"),this.editForm.closeForm()):(this.$editBtn.innerHTML=this.t("close"),this.editForm.openForm())},delete:function(e){e.preventDefault(),this.clearNotifications(this.$element),1==confirm(this.t("delete-confirm"))&&this.ajax(Comments.baseUrl+"trash",{method:"POST",data:this.serializeObject({commentId:this.commentId,siteId:this.siteId}),success:function(e){this.$element.parentNode.removeChild(this.$element)}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},flag:function(e){e.preventDefault(),this.clearNotifications(this.$element),this.ajax(Comments.baseUrl+"flag",{method:"POST",data:this.serializeObject({commentId:this.commentId,siteId:this.siteId}),success:function(e){this.toggleClass(this.$flagBtn.parentNode,"has-flag"),e.notice&&(console.log(e.notice),this.setNotifications("notice",this.$element,e.notice))}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},upvote:function(e){e.preventDefault(),this.ajax(Comments.baseUrl+"vote",{method:"POST",data:this.serializeObject({commentId:this.commentId,siteId:this.siteId,upvote:!0}),success:function(e){this.vote(!0)}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},downvote:function(e){e.preventDefault(),this.ajax(Comments.baseUrl+"vote",{method:"POST",data:this.serializeObject({commentId:this.commentId,siteId:this.siteId,downvote:!0}),success:function(e){this.vote(!1)}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},vote:function(e){var t=this.$element.querySelector('[data-role="likes"]'),n=parseInt(t.textContent,10);n||(n=0),e?n++:n--,0===n&&(n=""),t.textContent=n}}),Comments.ReplyForm=Comments.Base.extend({isOpen:!1,init:function(e){this.comment=e,this.instance=e.instance,this.$element=e.$element,this.$container=e.$replyContainer,this.$repliesContainer=e.$repliesContainer},setFormHtml:function(e){var t=this.instance.$baseForm.cloneNode(!0);
// Clear errors and info
this.clearNotifications(t),
// Clear all inputs
(t.querySelector('[name="fields[name]"]')||{}).value="",(t.querySelector('[name="fields[email]"]')||{}).value="",(t.querySelector('[name="fields[comment]"]')||{}).innerHTML="",
// Set the value to be the id of comment we're replying to
(t.querySelector('input[name="newParentId"]')||{}).value=this.comment.commentId,this.$container.innerHTML=t.outerHTML},openForm:function(e){this.setFormHtml(e),this.isOpen=!0,this.addListener(this.$container.querySelector('[role="form"]'),"submit",this.onSubmit,!1)},closeForm:function(){this.$container.innerHTML="",this.isOpen=!1},onSubmit:function(e){e.preventDefault(),this.postForm(e,"save",function(e){if(e.html){var t=this.createElement(e.html);
// Remove the form (empty the container)
this.remove(this.$container.firstChild),
// Prepend it to the original comment
this.$repliesContainer.insertBefore(t,this.$repliesContainer.firstChild),this.instance.comments[e.id]=new Comments.Comment(this.instance,t),this.comment.$replyBtn.innerHTML=this.t("reply"),this.isOpen=!1}}.bind(this))}}),Comments.EditForm=Comments.Base.extend({isOpen:!1,init:function(e){this.comment=e,this.instance=e.instance,this.$element=e.$element,this.$container=e.$replyContainer,this.$comment=this.$element.querySelector('[data-role="message"]'),this.commentText=this.$comment.innerHTML.replace(/<[^>]+>/g,"").trim()},setFormHtml:function(){var e=this.instance.$baseForm.cloneNode(!0);
// Clear errors and info
this.clearNotifications(e),
// Remove some stuff
this.remove(e.querySelector('[name="fields[name]"]')),this.remove(e.querySelector('[name="fields[email]"]')),this.remove(e.querySelector(".cc-i-figure")),
// Clear and update
e.querySelector('[name="fields[comment]"]').innerHTML=this.commentText,e.querySelector(".cc-f-btn").innerHTML=this.t("save"),
// Set the value to be the id of comment we're replying to
(e.querySelector('input[name="commentId"]')||{}).value=this.comment.commentId,this.$comment.innerHTML=e.outerHTML},openForm:function(){this.setFormHtml(),this.isOpen=!0,this.addListener(this.$comment.querySelector('[role="form"]'),"submit",this.onSubmit,!1)},closeForm:function(){var e;this.$element.querySelector('[data-role="message"]').innerHTML=this.commentText.replace(/\n/g,"<br>"),this.isOpen=!1},onSubmit:function(e){e.preventDefault(),this.postForm(e,"save",function(e){var t=this.$element.querySelector('[data-role="message"]'),n=this.$element.querySelector('[name="fields[comment]"]').value;t.innerHTML="<p>"+n.replace(/\n/g,"<br>\n")+"</p>",this.comment.editForm=new Comments.EditForm(this.comment),this.comment.$editBtn.innerHTML=this.t("edit"),this.isOpen=!1}.bind(this))}});
//# sourceMappingURL=comments.js.map