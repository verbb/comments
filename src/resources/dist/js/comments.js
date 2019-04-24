!function(){var e=this,t=!0,n=/xyz/.test(function(){xyz})?/\b__super__\b/:/.*/;Base=function(t){if(this==e)return Base.extend(t)},Base.extend=function(e){function i(){t&&this.init&&this.init.apply(this,arguments)}function s(e){for(var t in e)e.hasOwnProperty(t)&&(i[t]=e[t])}var o=this.prototype;t=!1;var r=new this;return t=!0,i.include=function(e){for(var t in e)if("include"==t)if(e[t]instanceof Array)for(var a=0,c=e[t].length;a<c;++a)i.include(e[t][a]);else i.include(e[t]);else if("extend"==t)if(e[t]instanceof Array)for(var a=0,c=e[t].length;a<c;++a)s(e[t][a]);else s(e[t]);else e.hasOwnProperty(t)&&(r[t]="function"==typeof e[t]&&"function"==typeof o[t]&&n.test(e[t])?function(e,t){return function(){return this.__super__=o[e],t.apply(this,arguments)}}(t,e[t]):e[t])},i.include(e),i.prototype=r,i.constructor=i,i.extend=arguments.callee,i}}(),Comments={},Comments.translations={},Comments.Base=Base.extend({addClass:function(e,t){e.classList?e.classList.add(t):e.className+=" "+t},removeClass:function(e,t){e.classList?e.classList.remove(t):e.className=e.className.replace(new RegExp("(^|\\b)"+t.split(" ").join("|")+"(\\b|$)","gi")," ")},toggleClass:function(e,t){if(e.classList)e.classList.toggle(t);else{var n=e.className.split(" "),i=n.indexOf(t);i>=0?n.splice(i,1):n.push(t),e.className=n.join(" ")}},createElement:function(e){var t=document.createElement("div");return t.innerHTML=e,t.firstChild},serialize:function(e){var t=[],n=e.querySelectorAll("input, select, textarea");return Array.prototype.forEach.call(n,function(e,n){t.push(encodeURIComponent(e.name)+"="+encodeURIComponent(e.value))}),t.push(encodeURIComponent(Comments.csrfTokenName)+"="+encodeURIComponent(Comments.csrfToken)),t.join("&")},serializeObject:function(e){var t=Object.keys(e).map(function(t){return encodeURIComponent(t)+"="+encodeURIComponent(e[t])});return t.push(encodeURIComponent(Comments.csrfTokenName)+"="+encodeURIComponent(Comments.csrfToken)),t.join("&")},ajax:function(e,t){t=t||{};var n=new XMLHttpRequest;n.open(t.method||"GET",e,!0),n.setRequestHeader("X-Requested-With","XMLHttpRequest"),n.setRequestHeader("Accept","application/json"),n.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),n.onreadystatechange=function(e){if(4===n.readyState){var i=JSON.parse(n.responseText);200===n.status&&t.success?i.errors?t.error(i):t.success(i):200!=n.status&&t.error&&(i.error&&(i=[[i.error]]),t.error(i))}},n.send(t.data||"")},addListener:function(e,t,n,i){e&&e.addEventListener(t,n.bind(this),i||!0)},remove:function(e){e&&e.parentNode.removeChild(e)},clearNotifications:function(e){var t=e.querySelectorAll('.cc-e, [data-role="notice"], [data-role="errors"]');t&&Array.prototype.forEach.call(t,function(e,t){e.innerHTML=""})},setNotifications:function(e,t,n){if(n&&t)if("error"===e){var i=n.errors||n;Object.keys(i).forEach(function(e){t.querySelector('[data-role="errors"]').innerHTML=i[e][0]})}else"validation"===e?Object.keys(n).forEach(function(e){t.querySelector('[name="fields['+e+']"]').nextElementSibling.innerHTML=n[e][0]}):t.querySelector('[data-role="notice"]').innerHTML=n},checkCaptcha:function(e,t){if(!Comments.recaptchaEnabled)return t(e,this);grecaptcha.execute(Comments.recaptchaKey,{action:"commentForm"}).then(function(n){return t(e+="&g-recaptcha-response="+n,this)})},postForm:function(e,t,n){var i=e.target,s=this.serialize(i),o=i.querySelector('[type="submit"]');this.clearNotifications(i),this.addClass(o,"loading"),this.checkCaptcha(s,function(e){this.ajax(Comments.baseUrl+t,{method:"POST",data:e,success:function(e){this.removeClass(o,"loading"),e.notice&&this.setNotifications("notice",i,e.notice),e.success?n(e):this.setNotifications("validation",i,e.errors)}.bind(this),error:function(e){this.removeClass(o,"loading"),this.setNotifications("validation",i,e.errors)}.bind(this)})}.bind(this))},t:function(e){return Comments.translations.hasOwnProperty(e)?Comments.translations[e]:""}}),Comments.Instance=Comments.Base.extend({comments:{},init:function(e,t){var n=document.querySelector(e),i=n.querySelectorAll('[data-role="comment"]');Comments.baseUrl=t.baseUrl+"/comments/comments/",Comments.csrfTokenName=t.csrfTokenName,Comments.csrfToken=t.csrfToken,Comments.translations=t.translations,Comments.recaptchaEnabled=t.recaptchaEnabled,Comments.recaptchaKey=t.recaptchaKey,this.$commentsContainer=n.querySelector('[data-role="comments"]'),this.$baseForm=n.querySelector('[data-role="form"]'),this.addListener(this.$baseForm,"submit",this.onSubmit,!1);for(var s=0;s<i.length;s++){var e=i[s].getAttribute("data-id");this.comments[e]=new Comments.Comment(this,i[s])}},onSubmit:function(e){e.preventDefault(),this.postForm(e,"save",function(e){if(e.html){var t=this.createElement(e.html),n=this.$commentsContainer.insertBefore(t,this.$commentsContainer.firstChild);this.comments[e.id]=new Comments.Comment(this,t),(this.$baseForm.querySelector('[name="fields[name]"]')||{}).value="",(this.$baseForm.querySelector('[name="fields[email]"]')||{}).value="",(this.$baseForm.querySelector('[name="fields[comment]"]')||{}).value="",location.hash="#comment-"+e.id}e.success&&((this.$baseForm.querySelector('[name="fields[name]"]')||{}).value="",(this.$baseForm.querySelector('[name="fields[email]"]')||{}).value="",(this.$baseForm.querySelector('[name="fields[comment]"]')||{}).value="")}.bind(this))}}),Comments.Comment=Comments.Base.extend({init:function(e,t){this.instance=e,this.$element=t,this.commentId=t.getAttribute("data-id"),this.siteId=t.getAttribute("data-site-id"),this.$replyContainer=t.querySelector('[data-role="reply"]'),this.$repliesContainer=t.querySelector('[data-role="replies"]'),this.$replyBtn=t.querySelector('[data-action="reply"]'),this.$editBtn=t.querySelector('[data-action="edit"]'),this.$deleteBtn=t.querySelector('[data-action="delete"]'),this.$flagBtn=t.querySelector('[data-action="flag"]'),this.$upvoteBtn=t.querySelector('[data-action="upvote"]'),this.$downvoteBtn=t.querySelector('[data-action="downvote"]'),this.replyForm=new Comments.ReplyForm(this),this.editForm=new Comments.EditForm(this),this.addListener(this.$replyBtn,"click",this.reply),this.addListener(this.$editBtn,"click",this.edit),this.addListener(this.$deleteBtn,"click",this.delete),this.addListener(this.$flagBtn,"click",this.flag),this.addListener(this.$upvoteBtn,"click",this.upvote),this.addListener(this.$downvoteBtn,"click",this.downvote)},reply:function(e){e.preventDefault(),this.replyForm.isOpen?(this.$replyBtn.innerHTML=this.t("reply"),this.replyForm.closeForm()):(this.$replyBtn.innerHTML=this.t("close"),this.replyForm.openForm())},edit:function(e){e.preventDefault(),this.editForm.isOpen?(this.$editBtn.innerHTML=this.t("edit"),this.editForm.closeForm()):(this.$editBtn.innerHTML=this.t("close"),this.editForm.openForm())},delete:function(e){e.preventDefault(),this.clearNotifications(this.$element),1==confirm(this.t("delete-confirm"))&&this.ajax(Comments.baseUrl+"trash",{method:"POST",data:this.serializeObject({commentId:this.commentId,siteId:this.siteId}),success:function(e){this.$element.parentNode.removeChild(this.$element)}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},flag:function(e){e.preventDefault(),this.clearNotifications(this.$element),this.ajax(Comments.baseUrl+"flag",{method:"POST",data:this.serializeObject({commentId:this.commentId,siteId:this.siteId}),success:function(e){this.toggleClass(this.$flagBtn.parentNode,"has-flag"),e.notice&&(console.log(e.notice),this.setNotifications("notice",this.$element,e.notice))}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},upvote:function(e){e.preventDefault(),this.ajax(Comments.baseUrl+"vote",{method:"POST",data:this.serializeObject({commentId:this.commentId,siteId:this.siteId,upvote:!0}),success:function(e){this.vote(!0)}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},downvote:function(e){e.preventDefault(),this.ajax(Comments.baseUrl+"vote",{method:"POST",data:this.serializeObject({commentId:this.commentId,siteId:this.siteId,downvote:!0}),success:function(e){this.vote(!1)}.bind(this),error:function(e){this.setNotifications("error",this.$element,e)}.bind(this)})},vote:function(e){var t=this.$element.querySelector('[data-role="likes"]'),n=parseInt(t.textContent,10);n||(n=0),e?n++:n--,0===n&&(n=""),t.textContent=n}}),Comments.ReplyForm=Comments.Base.extend({isOpen:!1,init:function(e){this.comment=e,this.instance=e.instance,this.$element=e.$element,this.$container=e.$replyContainer,this.$repliesContainer=e.$repliesContainer},setFormHtml:function(e){var t=this.instance.$baseForm.cloneNode(!0);this.clearNotifications(t),(t.querySelector('[name="fields[name]"]')||{}).value="",(t.querySelector('[name="fields[email]"]')||{}).value="",(t.querySelector('[name="fields[comment]"]')||{}).innerHTML="",(t.querySelector('input[name="newParentId"]')||{}).value=this.comment.commentId,this.$container.innerHTML=t.outerHTML},openForm:function(e){this.setFormHtml(e),this.isOpen=!0,this.addListener(this.$container.querySelector('[role="form"]'),"submit",this.onSubmit,!1)},closeForm:function(){this.$container.innerHTML="",this.isOpen=!1},onSubmit:function(e){e.preventDefault(),this.postForm(e,"save",function(e){if(e.html){var t=this.createElement(e.html);this.remove(this.$container.firstChild),this.$repliesContainer.insertBefore(t,this.$repliesContainer.firstChild),this.instance.comments[e.id]=new Comments.Comment(this.instance,t),this.comment.$replyBtn.innerHTML=this.t("reply"),this.isOpen=!1}e.success&&((this.$container.querySelector('[name="fields[name]"]')||{}).value="",(this.$container.querySelector('[name="fields[email]"]')||{}).value="",(this.$container.querySelector('[name="fields[comment]"]')||{}).value="")}.bind(this))}}),Comments.EditForm=Comments.Base.extend({isOpen:!1,init:function(e){this.comment=e,this.instance=e.instance,this.$element=e.$element,this.$container=e.$replyContainer,this.$comment=this.$element.querySelector('[data-role="message"]'),this.commentText=this.$comment.innerHTML.replace(/<[^>]+>/g,"").trim()},setFormHtml:function(){var e=this.instance.$baseForm.cloneNode(!0);this.clearNotifications(e),this.remove(e.querySelector('[name="fields[name]"]')),this.remove(e.querySelector('[name="fields[email]"]')),this.remove(e.querySelector(".cc-i-figure")),e.querySelector('[name="fields[comment]"]').innerHTML=this.commentText,e.querySelector(".cc-f-btn").innerHTML=this.t("save"),(e.querySelector('input[name="commentId"]')||{}).value=this.comment.commentId,this.$comment.innerHTML=e.outerHTML},openForm:function(){this.setFormHtml(),this.isOpen=!0,this.addListener(this.$comment.querySelector('[role="form"]'),"submit",this.onSubmit,!1)},closeForm:function(){var e;this.$element.querySelector('[data-role="message"]').innerHTML=this.commentText.replace(/\n/g,"<br>"),this.isOpen=!1},onSubmit:function(e){e.preventDefault(),this.postForm(e,"save",function(e){var t=this.$element.querySelector('[data-role="message"]'),n=this.$element.querySelector('[name="fields[comment]"]').value;t.innerHTML="<p>"+n.replace(/\n/g,"<br>\n")+"</p>",this.comment.editForm=new Comments.EditForm(this.comment),this.comment.$editBtn.innerHTML=this.t("edit"),this.isOpen=!1}.bind(this))}});
//# sourceMappingURL=comments.js.map