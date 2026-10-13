var yt,j,hi,Re,hn,di,hr,xr,dr,fr,fi,gt={},pi=[],mo=/acit|ex(?:s|g|n|p|$)|rph|grid|ows|mnc|ntw|ine[ch]|zoo|^ord|itera/i,Gt=Array.isArray;function ue(t,e){for(var r in e)t[r]=e[r];return t}function gi(t){t&&t.parentNode&&t.parentNode.removeChild(t)}function _o(t,e,r){var n,i,a,c={};for(a in e)a=="key"?n=e[a]:a=="ref"?i=e[a]:c[a]=e[a];if(arguments.length>2&&(c.children=arguments.length>3?yt.call(arguments,2):r),typeof t=="function"&&t.defaultProps!=null)for(a in t.defaultProps)c[a]===void 0&&(c[a]=t.defaultProps[a]);return lt(t,c,n,i,null)}function lt(t,e,r,n,i){var a={type:t,props:e,key:r,ref:n,__k:null,__:null,__b:0,__e:null,__d:void 0,__c:null,constructor:void 0,__v:i??++hi,__i:-1,__u:0};return i==null&&j.vnode!=null&&j.vnode(a),a}function Ch(){return{current:null}}function qt(t){return t.children}function ut(t,e){this.props=t,this.context=e}function qe(t,e){if(e==null)return t.__?qe(t.__,t.__i+1):null;for(var r;e<t.__k.length;e++)if((r=t.__k[e])!=null&&r.__e!=null)return r.__e;return typeof t.type=="function"?qe(t):null}function mi(t){var e,r;if((t=t.__)!=null&&t.__c!=null){for(t.__e=t.__c.base=null,e=0;e<t.__k.length;e++)if((r=t.__k[e])!=null&&r.__e!=null){t.__e=t.__c.base=r.__e;break}return mi(t)}}function pr(t){(!t.__d&&(t.__d=!0)&&Re.push(t)&&!Dt.__r++||hn!==j.debounceRendering)&&((hn=j.debounceRendering)||di)(Dt)}function Dt(){var t,e,r,n,i,a,c,u;for(Re.sort(hr);t=Re.shift();)t.__d&&(e=Re.length,n=void 0,a=(i=(r=t).__v).__e,c=[],u=[],r.__P&&((n=ue({},i)).__v=i.__v+1,j.vnode&&j.vnode(n),Cr(r.__P,n,i,r.__n,r.__P.namespaceURI,32&i.__u?[a]:null,c,a??qe(i),!!(32&i.__u),u),n.__v=i.__v,n.__.__k[n.__i]=n,yi(c,n,u),n.__e!=a&&mi(n)),Re.length>e&&Re.sort(hr));Dt.__r=0}function _i(t,e,r,n,i,a,c,u,d,f,w){var g,E,b,P,F,L=n&&n.__k||pi,N=e.length;for(r.__d=d,wo(r,e,L),d=r.__d,g=0;g<N;g++)(b=r.__k[g])!=null&&(E=b.__i===-1?gt:L[b.__i]||gt,b.__i=g,Cr(t,b,E,i,a,c,u,d,f,w),P=b.__e,b.ref&&E.ref!=b.ref&&(E.ref&&Pr(E.ref,null,b),w.push(b.ref,b.__c||P,b)),F==null&&P!=null&&(F=P),65536&b.__u||E.__k===b.__k?d=wi(b,d,t):typeof b.type=="function"&&b.__d!==void 0?d=b.__d:P&&(d=P.nextSibling),b.__d=void 0,b.__u&=-196609);r.__d=d,r.__e=F}function wo(t,e,r){var n,i,a,c,u,d=e.length,f=r.length,w=f,g=0;for(t.__k=[],n=0;n<d;n++)(i=e[n])!=null&&typeof i!="boolean"&&typeof i!="function"?(c=n+g,(i=t.__k[n]=typeof i=="string"||typeof i=="number"||typeof i=="bigint"||i.constructor==String?lt(null,i,null,null,null):Gt(i)?lt(qt,{children:i},null,null,null):i.constructor===void 0&&i.__b>0?lt(i.type,i.props,i.key,i.ref?i.ref:null,i.__v):i).__=t,i.__b=t.__b+1,a=null,(u=i.__i=vo(i,r,c,w))!==-1&&(w--,(a=r[u])&&(a.__u|=131072)),a==null||a.__v===null?(u==-1&&g--,typeof i.type!="function"&&(i.__u|=65536)):u!==c&&(u==c-1?g--:u==c+1?g++:(u>c?g--:g++,i.__u|=65536))):i=t.__k[n]=null;if(w)for(n=0;n<f;n++)(a=r[n])!=null&&(131072&a.__u)==0&&(a.__e==t.__d&&(t.__d=qe(a)),vi(a,a))}function wi(t,e,r){var n,i;if(typeof t.type=="function"){for(n=t.__k,i=0;n&&i<n.length;i++)n[i]&&(n[i].__=t,e=wi(n[i],e,r));return e}t.__e!=e&&(e&&t.type&&!r.contains(e)&&(e=qe(t)),r.insertBefore(t.__e,e||null),e=t.__e);do e=e&&e.nextSibling;while(e!=null&&e.nodeType===8);return e}function yo(t,e){return e=e||[],t==null||typeof t=="boolean"||(Gt(t)?t.some(function(r){yo(r,e)}):e.push(t)),e}function vo(t,e,r,n){var i=t.key,a=t.type,c=r-1,u=r+1,d=e[r];if(d===null||d&&i==d.key&&a===d.type&&(131072&d.__u)==0)return r;if(n>(d!=null&&(131072&d.__u)==0?1:0))for(;c>=0||u<e.length;){if(c>=0){if((d=e[c])&&(131072&d.__u)==0&&i==d.key&&a===d.type)return c;c--}if(u<e.length){if((d=e[u])&&(131072&d.__u)==0&&i==d.key&&a===d.type)return u;u++}}return-1}function dn(t,e,r){e[0]==="-"?t.setProperty(e,r??""):t[e]=r==null?"":typeof r!="number"||mo.test(e)?r:r+"px"}function kt(t,e,r,n,i){var a;e:if(e==="style")if(typeof r=="string")t.style.cssText=r;else{if(typeof n=="string"&&(t.style.cssText=n=""),n)for(e in n)r&&e in r||dn(t.style,e,"");if(r)for(e in r)n&&r[e]===n[e]||dn(t.style,e,r[e])}else if(e[0]==="o"&&e[1]==="n")a=e!==(e=e.replace(/(PointerCapture)$|Capture$/i,"$1")),e=e.toLowerCase()in t||e==="onFocusOut"||e==="onFocusIn"?e.toLowerCase().slice(2):e.slice(2),t.l||(t.l={}),t.l[e+a]=r,r?n?r.u=n.u:(r.u=xr,t.addEventListener(e,a?fr:dr,a)):t.removeEventListener(e,a?fr:dr,a);else{if(i=="http://www.w3.org/2000/svg")e=e.replace(/xlink(H|:h)/,"h").replace(/sName$/,"s");else if(e!="width"&&e!="height"&&e!="href"&&e!="list"&&e!="form"&&e!="tabIndex"&&e!="download"&&e!="rowSpan"&&e!="colSpan"&&e!="role"&&e!="popover"&&e in t)try{t[e]=r??"";break e}catch{}typeof r=="function"||(r==null||r===!1&&e[4]!=="-"?t.removeAttribute(e):t.setAttribute(e,e=="popover"&&r==1?"":r))}}function fn(t){return function(e){if(this.l){var r=this.l[e.type+t];if(e.t==null)e.t=xr++;else if(e.t<r.u)return;return r(j.event?j.event(e):e)}}}function Cr(t,e,r,n,i,a,c,u,d,f){var w,g,E,b,P,F,L,N,A,S,I,D,R,x,v,T,k=e.type;if(e.constructor!==void 0)return null;128&r.__u&&(d=!!(32&r.__u),a=[u=e.__e=r.__e]),(w=j.__b)&&w(e);e:if(typeof k=="function")try{if(N=e.props,A="prototype"in k&&k.prototype.render,S=(w=k.contextType)&&n[w.__c],I=w?S?S.props.value:w.__:n,r.__c?L=(g=e.__c=r.__c).__=g.__E:(A?e.__c=g=new k(N,I):(e.__c=g=new ut(N,I),g.constructor=k,g.render=Ao),S&&S.sub(g),g.props=N,g.state||(g.state={}),g.context=I,g.__n=n,E=g.__d=!0,g.__h=[],g._sb=[]),A&&g.__s==null&&(g.__s=g.state),A&&k.getDerivedStateFromProps!=null&&(g.__s==g.state&&(g.__s=ue({},g.__s)),ue(g.__s,k.getDerivedStateFromProps(N,g.__s))),b=g.props,P=g.state,g.__v=e,E)A&&k.getDerivedStateFromProps==null&&g.componentWillMount!=null&&g.componentWillMount(),A&&g.componentDidMount!=null&&g.__h.push(g.componentDidMount);else{if(A&&k.getDerivedStateFromProps==null&&N!==b&&g.componentWillReceiveProps!=null&&g.componentWillReceiveProps(N,I),!g.__e&&(g.shouldComponentUpdate!=null&&g.shouldComponentUpdate(N,g.__s,I)===!1||e.__v===r.__v)){for(e.__v!==r.__v&&(g.props=N,g.state=g.__s,g.__d=!1),e.__e=r.__e,e.__k=r.__k,e.__k.some(function(C){C&&(C.__=e)}),D=0;D<g._sb.length;D++)g.__h.push(g._sb[D]);g._sb=[],g.__h.length&&c.push(g);break e}g.componentWillUpdate!=null&&g.componentWillUpdate(N,g.__s,I),A&&g.componentDidUpdate!=null&&g.__h.push(function(){g.componentDidUpdate(b,P,F)})}if(g.context=I,g.props=N,g.__P=t,g.__e=!1,R=j.__r,x=0,A){for(g.state=g.__s,g.__d=!1,R&&R(e),w=g.render(g.props,g.state,g.context),v=0;v<g._sb.length;v++)g.__h.push(g._sb[v]);g._sb=[]}else do g.__d=!1,R&&R(e),w=g.render(g.props,g.state,g.context),g.state=g.__s;while(g.__d&&++x<25);g.state=g.__s,g.getChildContext!=null&&(n=ue(ue({},n),g.getChildContext())),A&&!E&&g.getSnapshotBeforeUpdate!=null&&(F=g.getSnapshotBeforeUpdate(b,P)),_i(t,Gt(T=w!=null&&w.type===qt&&w.key==null?w.props.children:w)?T:[T],e,r,n,i,a,c,u,d,f),g.base=e.__e,e.__u&=-161,g.__h.length&&c.push(g),L&&(g.__E=g.__=null)}catch(C){if(e.__v=null,d||a!=null){for(e.__u|=d?160:128;u&&u.nodeType===8&&u.nextSibling;)u=u.nextSibling;a[a.indexOf(u)]=null,e.__e=u}else e.__e=r.__e,e.__k=r.__k;j.__e(C,e,r)}else a==null&&e.__v===r.__v?(e.__k=r.__k,e.__e=r.__e):e.__e=bo(r.__e,e,r,n,i,a,c,d,f);(w=j.diffed)&&w(e)}function yi(t,e,r){e.__d=void 0;for(var n=0;n<r.length;n++)Pr(r[n],r[++n],r[++n]);j.__c&&j.__c(e,t),t.some(function(i){try{t=i.__h,i.__h=[],t.some(function(a){a.call(i)})}catch(a){j.__e(a,i.__v)}})}function bo(t,e,r,n,i,a,c,u,d){var f,w,g,E,b,P,F,L=r.props,N=e.props,A=e.type;if(A==="svg"?i="http://www.w3.org/2000/svg":A==="math"?i="http://www.w3.org/1998/Math/MathML":i||(i="http://www.w3.org/1999/xhtml"),a!=null){for(f=0;f<a.length;f++)if((b=a[f])&&"setAttribute"in b==!!A&&(A?b.localName===A:b.nodeType===3)){t=b,a[f]=null;break}}if(t==null){if(A===null)return document.createTextNode(N);t=document.createElementNS(i,A,N.is&&N),u&&(j.__m&&j.__m(e,a),u=!1),a=null}if(A===null)L===N||u&&t.data===N||(t.data=N);else{if(a=a&&yt.call(t.childNodes),L=r.props||gt,!u&&a!=null)for(L={},f=0;f<t.attributes.length;f++)L[(b=t.attributes[f]).name]=b.value;for(f in L)if(b=L[f],f!="children"){if(f=="dangerouslySetInnerHTML")g=b;else if(!(f in N)){if(f=="value"&&"defaultValue"in N||f=="checked"&&"defaultChecked"in N)continue;kt(t,f,null,b,i)}}for(f in N)b=N[f],f=="children"?E=b:f=="dangerouslySetInnerHTML"?w=b:f=="value"?P=b:f=="checked"?F=b:u&&typeof b!="function"||L[f]===b||kt(t,f,b,L[f],i);if(w)u||g&&(w.__html===g.__html||w.__html===t.innerHTML)||(t.innerHTML=w.__html),e.__k=[];else if(g&&(t.innerHTML=""),_i(t,Gt(E)?E:[E],e,r,n,A==="foreignObject"?"http://www.w3.org/1999/xhtml":i,a,c,a?a[0]:r.__k&&qe(r,0),u,d),a!=null)for(f=a.length;f--;)gi(a[f]);u||(f="value",A==="progress"&&P==null?t.removeAttribute("value"):P!==void 0&&(P!==t[f]||A==="progress"&&!P||A==="option"&&P!==L[f])&&kt(t,f,P,L[f],i),f="checked",F!==void 0&&F!==t[f]&&kt(t,f,F,L[f],i))}return t}function Pr(t,e,r){try{if(typeof t=="function"){var n=typeof t.__u=="function";n&&t.__u(),n&&e==null||(t.__u=t(e))}else t.current=e}catch(i){j.__e(i,r)}}function vi(t,e,r){var n,i;if(j.unmount&&j.unmount(t),(n=t.ref)&&(n.current&&n.current!==t.__e||Pr(n,null,e)),(n=t.__c)!=null){if(n.componentWillUnmount)try{n.componentWillUnmount()}catch(a){j.__e(a,e)}n.base=n.__P=null}if(n=t.__k)for(i=0;i<n.length;i++)n[i]&&vi(n[i],e,r||typeof t.type!="function");r||gi(t.__e),t.__c=t.__=t.__e=t.__d=void 0}function Ao(t,e,r){return this.constructor(t,r)}function Io(t,e,r){var n,i,a,c;j.__&&j.__(t,e),i=(n=typeof r=="function")?null:r&&r.__k||e.__k,a=[],c=[],Cr(e,t=(!n&&r||e).__k=_o(qt,null,[t]),i||gt,gt,e.namespaceURI,!n&&r?[r]:i?null:e.firstChild?yt.call(e.childNodes):null,a,!n&&r?r:i?i.__e:e.firstChild,n,c),yi(a,t,c)}function Eo(t,e){Io(t,e,Eo)}function Ph(t,e,r){var n,i,a,c,u=ue({},t.props);for(a in t.type&&t.type.defaultProps&&(c=t.type.defaultProps),e)a=="key"?n=e[a]:a=="ref"?i=e[a]:u[a]=e[a]===void 0&&c!==void 0?c[a]:e[a];return arguments.length>2&&(u.children=arguments.length>3?yt.call(arguments,2):r),lt(t.type,u,n||t.key,i||t.ref,null)}function To(t,e){var r={__c:e="__cC"+fi++,__:t,Consumer:function(n,i){return n.children(i)},Provider:function(n){var i,a;return this.getChildContext||(i=new Set,(a={})[e]=this,this.getChildContext=function(){return a},this.componentWillUnmount=function(){i=null},this.shouldComponentUpdate=function(c){this.props.value!==c.value&&i.forEach(function(u){u.__e=!0,pr(u)})},this.sub=function(c){i.add(c);var u=c.componentWillUnmount;c.componentWillUnmount=function(){i&&i.delete(c),u&&u.call(c)}}),n.children}};return r.Provider.__=r.Consumer.contextType=r}yt=pi.slice,j={__e:function(t,e,r,n){for(var i,a,c;e=e.__;)if((i=e.__c)&&!i.__)try{if((a=i.constructor)&&a.getDerivedStateFromError!=null&&(i.setState(a.getDerivedStateFromError(t)),c=i.__d),i.componentDidCatch!=null&&(i.componentDidCatch(t,n||{}),c=i.__d),c)return i.__E=i}catch(u){t=u}throw t}},hi=0,ut.prototype.setState=function(t,e){var r;r=this.__s!=null&&this.__s!==this.state?this.__s:this.__s=ue({},this.state),typeof t=="function"&&(t=t(ue({},r),this.props)),t&&ue(r,t),t!=null&&this.__v&&(e&&this._sb.push(e),pr(this))},ut.prototype.forceUpdate=function(t){this.__v&&(this.__e=!0,t&&this.__h.push(t),pr(this))},ut.prototype.render=qt,Re=[],di=typeof Promise=="function"?Promise.prototype.then.bind(Promise.resolve()):setTimeout,hr=function(t,e){return t.__v.__b-e.__v.__b},Dt.__r=0,xr=0,dr=fn(!1),fr=fn(!0),fi=0;const Rr=t=>{if(t.stack){let e=t.stack;if(t.cause){const r=Rr(t.cause);e+=`
Caused by ${r}`}return e}return""},So=void 0;function ko(t,e){return{...t,...e,properties:{...t.properties,...e.properties}}}class ce{def;stackCleaner=e=>e;static SHARED_SERVICE_URLS={}.LOCAL_SHR?["http://localhost:5001/dev-shared-services/us-central1/api"]:{}.STAGING!=="true"?["https://api.divriots.com","https://api-eu.divriots.com"]:["https://api-oddhqn4pmq-uc.a.run.app","https://apieu-oddhqn4pmq-ew.a.run.app"];static serviceUrl;constructor(e){this.def=e,{}.K_REVISION&&this.addDefault({properties:{krevision:{}.K_REVISION}})}addDefault(e){const r=this.resolveDefaults();if(r){if(r.properties){const{properties:n,...i}=e;Object.assign(r.properties,n),e=i}Object.assign(r,e)}else this.def={...e}}resetDefault(){this.def=void 0}transformOptions(e){if(!e)return;const{country:r,userAgent:n}=e,i={};return r&&(i.co=r),n&&(i.ua=n),i}async feature_test(e,r){if("feature"in r){console.error("`feature` is a reserved property for feature_test tracking.");return}return this.track("feature-test",{properties:{feature:e,...r}})}async exception(e,r,n){({}).FIREBASE_CONFIG&&console.error(`Analytics.exception(): ${e}`,r,n);const i={name:e};return n&&(i.details=typeof n=="string"?n:JSON.stringify(n)),this.track("exception",{error:r,properties:i})}catcher(e,r){return n=>{this.exception(e,n,r)}}unhandled(e,r){try{const n=r();return n&&typeof n=="object"&&"catch"in n?n.catch(this.catcher(e)):n}catch(n){this.exception(e,n)}}setStackCleaner(e){this.stackCleaner=e}resolveDefaults(){return this.def}async track(e,r={},n){const i=this.resolveDefaults(),a={...i?.properties,...r.properties};if(r.error){const d=r.error;a.error_message=d.message||String(d),a.error_stack=this.stackCleaner(Rr(d)),delete r.error}const c={event:e,version:{}.__FIGMA_PLUGIN_VERSION__,...i,...r,properties:a,options:this.transformOptions(n)};if(!c.product){console.error("Analytics.track(): `product` property is missing.");return}const u=async(d,f)=>{try{return await fetch(`${d}/pa`,{method:"POST",body:JSON.stringify(c)}),!0}catch(w){f&&console.error(`Analytics.track(): Unexpected error on event ${e}.`,w)}return!1};if(ce.serviceUrl)await u(ce.serviceUrl,!0);else{const d=[...ce.SHARED_SERVICE_URLS];for(;d.length;){const f=d.shift();if(await u(f,!d.length)){ce.serviceUrl=f;return}}ce.serviceUrl||(ce.serviceUrl=ce.SHARED_SERVICE_URLS[0])}}}class xo extends ce{isInitialized=!1;constructor(){super({})}initialize(e){this.addDefault(e),this.isInitialized=!0}runWith(e,r,...n){{const i=new Error("AsyncLocalStorage is not available");return Ee.exception("analytics:runWith",i),r(...n)}}resolveDefaults(){const e=So?.getStore(),r=super.resolveDefaults();return e?ko(r??{},e):r}}const Ee=new xo;function Co(){let t,e;return{promise:new Promise((n,i)=>{t=n,e=i}),resolve:t,error:e}}const Po=t=>typeof t?.then=="function",Ro=(t,e)=>new Promise((r,n)=>setTimeout(()=>{e&&"errorOnTimeout"in e?n(new Error(e.errorOnTimeout)):r(e?.value)},t));function Oo(t,e,r){const n=Date.now(),i=u=>r?.({result:u,name:t,duration:Date.now()-n}),a=u=>(i({ret:u,failed:!1}),u),c=u=>{throw i({error:u,failed:!0}),u};try{const u=e();return Po(u)?u.then(a).catch(c):a(u)}catch(u){return c(u)}}const No=(t,e,r)=>Oo(t,e,n=>{console.log(`${t}${n.result.failed?" (errored)":""}: ${n.duration}ms`),r?.(n)});let bi;const Rh=t=>{bi=t};function Qe(t,e,r){const n=t,i=e,{source:a,retry:c,tracking:u,analytics:d,extraTrackingProps:f,fallbacks:w,fetchFn:g,...E}=i??{},b=d||Ee.isInitialized&&Ee,P=a||bi;if(!P&&u!==!1){const A="No source provided and defaultSource has not been set";throw console.error(A),new Error(A)}const F=A=>Array.isArray(c)&&c.includes(A),L=r,N=A=>{let S=n,I=typeof n=="object"&&"url"in n?n.url:String(n);if(A&&w)if(I=Mo(I,w),typeof S=="string")S=I;else throw new Error("Retry with fallback only supported for string requests");const D=()=>{if(!g)return fetch(S,E);if(typeof S!="string")throw new Error("fetchFn only supported for string requests");return g(S,E)};return No(`fetch ${S}${A?" (retry)":""}`,D,async({duration:R,result:x})=>{const v=u??"both";if(v===!1)return;const T="ret"in x,k=T&&x.ret.ok;if(!(v==="success"&&!k||v==="error"&&k)&&b){const C={...f,url:I,duration:R,retry:A,source:P},m=E.method&&E.method.toLowerCase();if(m!=="get"&&(C.method=E.method),(m==="post"||m==="put")&&E.body){const B=Or(E.body);B!==void 0&&(C.requestPayloadSize=B)}if(T){const B=x.ret,W={...C,code:x.ret.status},O=!k&&await Do(B).catch(()=>"");O&&(W.message=O),pn(b,W)}else pn(b,{...C,message:x.error?.message||x.error})}}).then(R=>{if(!A&&F(R.status))throw new Error("Retry");return R}).then(R=>L?L(R):R)};return N().catch(A=>{if(A.name==="AbortError"||!c)throw A;return N(!0)})}function Or(t){if(!!t){if(typeof t=="string")return new TextEncoder().encode(t).length;if(typeof t=="object"){if(t instanceof FormData)return Array.from(t.values()).reduce((e,r)=>e+(Or(r)||0),0);if("length"in t)return t.length;if("size"in t)return t.size;if("byteLength"in t)return t.byteLength}}}function pn(t,e){t.track("fetch",{properties:{...e,type:"message"in e?"error":"success"}},{country:!0})}const gn=16*1024;async function Do(t){const e=t.clone().body?.getReader();if(!e)return"";const r=new TextDecoder;let n="";try{for(;n.length<gn;){const{done:i,value:a}=await e.read();if(i)break;n+=r.decode(a,{stream:!0})}}finally{e.cancel().catch(()=>{})}return n.slice(0,gn)}function Mo(t,e){for(const[r,n]of Object.entries(e)){const i=t.replace(r,n);if(i!==t)return i}return t}var Te,z,rr,mn,Ke=0,Ai=[],q=j,_n=q.__b,wn=q.__r,yn=q.diffed,vn=q.__c,bn=q.unmount,An=q.__;function et(t,e){q.__h&&q.__h(z,t,Ke||e),Ke=0;var r=z.__H||(z.__H={__:[],__h:[]});return t>=r.__.length&&r.__.push({}),r.__[t]}function Lo(t){return Ke=1,Uo(Ti,t)}function Uo(t,e,r){var n=et(Te++,2);if(n.t=t,!n.__c&&(n.__=[r?r(e):Ti(void 0,e),function(u){var d=n.__N?n.__N[0]:n.__[0],f=n.t(d,u);d!==f&&(n.__N=[f,n.__[1]],n.__c.setState({}))}],n.__c=z,!z.u)){var i=function(u,d,f){if(!n.__c.__H)return!0;var w=n.__c.__H.__.filter(function(E){return!!E.__c});if(w.every(function(E){return!E.__N}))return!a||a.call(this,u,d,f);var g=!1;return w.forEach(function(E){if(E.__N){var b=E.__[0];E.__=E.__N,E.__N=void 0,b!==E.__[0]&&(g=!0)}}),!(!g&&n.__c.props===u)&&(!a||a.call(this,u,d,f))};z.u=!0;var a=z.shouldComponentUpdate,c=z.componentWillUpdate;z.componentWillUpdate=function(u,d,f){if(this.__e){var w=a;a=void 0,i(u,d,f),a=w}c&&c.call(this,u,d,f)},z.shouldComponentUpdate=i}return n.__N||n.__}function Ii(t,e){var r=et(Te++,3);!q.__s&&Nr(r.__H,e)&&(r.__=t,r.i=e,z.__H.__h.push(r))}function Bo(t,e){var r=et(Te++,4);!q.__s&&Nr(r.__H,e)&&(r.__=t,r.i=e,z.__h.push(r))}function Ei(t){return Ke=5,Kt(function(){return{current:t}},[])}function Oh(t,e,r){Ke=6,Bo(function(){return typeof t=="function"?(t(e()),function(){return t(null)}):t?(t.current=e(),function(){return t.current=null}):void 0},r==null?r:r.concat(t))}function Kt(t,e){var r=et(Te++,7);return Nr(r.__H,e)&&(r.__=t(),r.__H=e,r.__h=t),r.__}function Fo(t,e){return Ke=8,Kt(function(){return t},e)}function Nh(t){var e=z.context[t.__c],r=et(Te++,9);return r.c=t,e?(r.__==null&&(r.__=!0,e.sub(z)),e.props.value):t.__}function Dh(t,e){q.useDebugValue&&q.useDebugValue(e?e(t):t)}function Mh(){var t=et(Te++,11);if(!t.__){for(var e=z.__v;e!==null&&!e.__m&&e.__!==null;)e=e.__;var r=e.__m||(e.__m=[0,0]);t.__="P"+r[0]+"-"+r[1]++}return t.__}function $o(){for(var t;t=Ai.shift();)if(t.__P&&t.__H)try{t.__H.__h.forEach(Ct),t.__H.__h.forEach(gr),t.__H.__h=[]}catch(e){t.__H.__h=[],q.__e(e,t.__v)}}q.__b=function(t){z=null,_n&&_n(t)},q.__=function(t,e){t&&e.__k&&e.__k.__m&&(t.__m=e.__k.__m),An&&An(t,e)},q.__r=function(t){wn&&wn(t),Te=0;var e=(z=t.__c).__H;e&&(rr===z?(e.__h=[],z.__h=[],e.__.forEach(function(r){r.__N&&(r.__=r.__N),r.i=r.__N=void 0})):(e.__h.forEach(Ct),e.__h.forEach(gr),e.__h=[],Te=0)),rr=z},q.diffed=function(t){yn&&yn(t);var e=t.__c;e&&e.__H&&(e.__H.__h.length&&(Ai.push(e)!==1&&mn===q.requestAnimationFrame||((mn=q.requestAnimationFrame)||Ho)($o)),e.__H.__.forEach(function(r){r.i&&(r.__H=r.i),r.i=void 0})),rr=z=null},q.__c=function(t,e){e.some(function(r){try{r.__h.forEach(Ct),r.__h=r.__h.filter(function(n){return!n.__||gr(n)})}catch(n){e.some(function(i){i.__h&&(i.__h=[])}),e=[],q.__e(n,r.__v)}}),vn&&vn(t,e)},q.unmount=function(t){bn&&bn(t);var e,r=t.__c;r&&r.__H&&(r.__H.__.forEach(function(n){try{Ct(n)}catch(i){e=i}}),r.__H=void 0,e&&q.__e(e,r.__v))};var In=typeof requestAnimationFrame=="function";function Ho(t){var e,r=function(){clearTimeout(n),In&&cancelAnimationFrame(e),setTimeout(t)},n=setTimeout(r,100);In&&(e=requestAnimationFrame(r))}function Ct(t){var e=z,r=t.__c;typeof r=="function"&&(t.__c=void 0,r()),z=e}function gr(t){var e=z;t.__c=t.__(),z=e}function Nr(t,e){return!t||t.length!==e.length||e.some(function(r,n){return r!==t[n]})}function Ti(t,e){return typeof e=="function"?e(t):e}const Lh=Intl.NumberFormat("en").format,Vo=(t,e)=>e===1?t:`${t}s`,Uh=t=>!!t&&(t.full||!!t.flow),Bh=()=>({paused:!1,steps:[]}),Fh=({steps:t})=>`${t.length} ${Vo("step",t.length)}`;class jo extends Error{url;constructor(e){super("Allow access to file URLs authorization is necessary for local files, please enable it in extension settings"),this.name="FileAccessRequiredError",this.url=e}}async function $h(){const t=await chrome.tabs.query({active:!0,currentWindow:!0}).then(e=>e[0]);if(!t)throw new Error("Unable to get active tab");if(!t.id||!t.url)throw new Error("Unable to get required tab metadata");if(t.url.includes("chrome://"))throw new Error("html.to.design cannot capture chrome:// pages");if(t.url.startsWith("file://")&&!await chrome.extension.isAllowedFileSchemeAccess())throw new jo(t.url);return{tabId:t.id,url:t.url}}var Wo=typeof globalThis<"u"?globalThis:typeof window<"u"?window:typeof global<"u"?global:typeof self<"u"?self:{};function Si(t){return t&&t.__esModule&&Object.prototype.hasOwnProperty.call(t,"default")?t.default:t}var Je={exports:{}};const zo=t=>typeof crypto<"u"&&typeof crypto.getRandomValues=="function"?()=>{const e=crypto.getRandomValues(new Uint8Array(1))[0];return(e>=t?e%t:e).toString(t)}:()=>Math.floor(Math.random()*t).toString(t),ki=(t=7,e=!1)=>Array.from({length:t},zo(e?16:36)).join("");Je.exports=ki;Je.exports.default=ki;var Hh=()=>`uid::${Je.exports(7)}`,xi=(t,e=["endpointName","fingerprint"])=>typeof t=="object"&&t!==null&&e.every(r=>r in t),Vh=t=>{if(!xi(t))throw new TypeError("Invalid connection args");return JSON.stringify(t)},jh=t=>{try{const e=JSON.parse(t);return xi(e)?e:null}catch{return null}},Wh=()=>{let t=[];return{add:(...e)=>{t=[...t,...e]},remove:e=>{t=typeof e=="string"?t.filter(r=>r.message.transactionId!==e):t.filter(r=>!e.includes(r))},entries:()=>t}},zh=class{static toBackground(t,e){return t.postMessage(e)}static toExtensionContext(t,e){return t.postMessage(e)}},Ci={exports:{}};(function(t,e){(function(r,n){n(t)})(typeof globalThis<"u"?globalThis:typeof self<"u"?self:Wo,function(r){if(typeof globalThis!="object"||typeof chrome!="object"||!chrome||!chrome.runtime||!chrome.runtime.id)throw new Error("This script should only be loaded in a browser extension.");if(typeof globalThis.browser>"u"||Object.getPrototypeOf(globalThis.browser)!==Object.prototype){const n="The message port closed before a response was received.",i="Returning a Promise is the preferred way to send a reply from an onMessage/onMessageExternal listener, as the sendResponse will be removed from the specs (See https://developer.mozilla.org/docs/Mozilla/Add-ons/WebExtensions/API/runtime/onMessage)",a=c=>{const u={alarms:{clear:{minArgs:0,maxArgs:1},clearAll:{minArgs:0,maxArgs:0},get:{minArgs:0,maxArgs:1},getAll:{minArgs:0,maxArgs:0}},bookmarks:{create:{minArgs:1,maxArgs:1},get:{minArgs:1,maxArgs:1},getChildren:{minArgs:1,maxArgs:1},getRecent:{minArgs:1,maxArgs:1},getSubTree:{minArgs:1,maxArgs:1},getTree:{minArgs:0,maxArgs:0},move:{minArgs:2,maxArgs:2},remove:{minArgs:1,maxArgs:1},removeTree:{minArgs:1,maxArgs:1},search:{minArgs:1,maxArgs:1},update:{minArgs:2,maxArgs:2}},browserAction:{disable:{minArgs:0,maxArgs:1,fallbackToNoCallback:!0},enable:{minArgs:0,maxArgs:1,fallbackToNoCallback:!0},getBadgeBackgroundColor:{minArgs:1,maxArgs:1},getBadgeText:{minArgs:1,maxArgs:1},getPopup:{minArgs:1,maxArgs:1},getTitle:{minArgs:1,maxArgs:1},openPopup:{minArgs:0,maxArgs:0},setBadgeBackgroundColor:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0},setBadgeText:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0},setIcon:{minArgs:1,maxArgs:1},setPopup:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0},setTitle:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0}},browsingData:{remove:{minArgs:2,maxArgs:2},removeCache:{minArgs:1,maxArgs:1},removeCookies:{minArgs:1,maxArgs:1},removeDownloads:{minArgs:1,maxArgs:1},removeFormData:{minArgs:1,maxArgs:1},removeHistory:{minArgs:1,maxArgs:1},removeLocalStorage:{minArgs:1,maxArgs:1},removePasswords:{minArgs:1,maxArgs:1},removePluginData:{minArgs:1,maxArgs:1},settings:{minArgs:0,maxArgs:0}},commands:{getAll:{minArgs:0,maxArgs:0}},contextMenus:{remove:{minArgs:1,maxArgs:1},removeAll:{minArgs:0,maxArgs:0},update:{minArgs:2,maxArgs:2}},cookies:{get:{minArgs:1,maxArgs:1},getAll:{minArgs:1,maxArgs:1},getAllCookieStores:{minArgs:0,maxArgs:0},remove:{minArgs:1,maxArgs:1},set:{minArgs:1,maxArgs:1}},devtools:{inspectedWindow:{eval:{minArgs:1,maxArgs:2,singleCallbackArg:!1}},panels:{create:{minArgs:3,maxArgs:3,singleCallbackArg:!0},elements:{createSidebarPane:{minArgs:1,maxArgs:1}}}},downloads:{cancel:{minArgs:1,maxArgs:1},download:{minArgs:1,maxArgs:1},erase:{minArgs:1,maxArgs:1},getFileIcon:{minArgs:1,maxArgs:2},open:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0},pause:{minArgs:1,maxArgs:1},removeFile:{minArgs:1,maxArgs:1},resume:{minArgs:1,maxArgs:1},search:{minArgs:1,maxArgs:1},show:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0}},extension:{isAllowedFileSchemeAccess:{minArgs:0,maxArgs:0},isAllowedIncognitoAccess:{minArgs:0,maxArgs:0}},history:{addUrl:{minArgs:1,maxArgs:1},deleteAll:{minArgs:0,maxArgs:0},deleteRange:{minArgs:1,maxArgs:1},deleteUrl:{minArgs:1,maxArgs:1},getVisits:{minArgs:1,maxArgs:1},search:{minArgs:1,maxArgs:1}},i18n:{detectLanguage:{minArgs:1,maxArgs:1},getAcceptLanguages:{minArgs:0,maxArgs:0}},identity:{launchWebAuthFlow:{minArgs:1,maxArgs:1}},idle:{queryState:{minArgs:1,maxArgs:1}},management:{get:{minArgs:1,maxArgs:1},getAll:{minArgs:0,maxArgs:0},getSelf:{minArgs:0,maxArgs:0},setEnabled:{minArgs:2,maxArgs:2},uninstallSelf:{minArgs:0,maxArgs:1}},notifications:{clear:{minArgs:1,maxArgs:1},create:{minArgs:1,maxArgs:2},getAll:{minArgs:0,maxArgs:0},getPermissionLevel:{minArgs:0,maxArgs:0},update:{minArgs:2,maxArgs:2}},pageAction:{getPopup:{minArgs:1,maxArgs:1},getTitle:{minArgs:1,maxArgs:1},hide:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0},setIcon:{minArgs:1,maxArgs:1},setPopup:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0},setTitle:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0},show:{minArgs:1,maxArgs:1,fallbackToNoCallback:!0}},permissions:{contains:{minArgs:1,maxArgs:1},getAll:{minArgs:0,maxArgs:0},remove:{minArgs:1,maxArgs:1},request:{minArgs:1,maxArgs:1}},runtime:{getBackgroundPage:{minArgs:0,maxArgs:0},getPlatformInfo:{minArgs:0,maxArgs:0},openOptionsPage:{minArgs:0,maxArgs:0},requestUpdateCheck:{minArgs:0,maxArgs:0},sendMessage:{minArgs:1,maxArgs:3},sendNativeMessage:{minArgs:2,maxArgs:2},setUninstallURL:{minArgs:1,maxArgs:1}},sessions:{getDevices:{minArgs:0,maxArgs:1},getRecentlyClosed:{minArgs:0,maxArgs:1},restore:{minArgs:0,maxArgs:1}},storage:{local:{clear:{minArgs:0,maxArgs:0},get:{minArgs:0,maxArgs:1},getBytesInUse:{minArgs:0,maxArgs:1},remove:{minArgs:1,maxArgs:1},set:{minArgs:1,maxArgs:1}},managed:{get:{minArgs:0,maxArgs:1},getBytesInUse:{minArgs:0,maxArgs:1}},sync:{clear:{minArgs:0,maxArgs:0},get:{minArgs:0,maxArgs:1},getBytesInUse:{minArgs:0,maxArgs:1},remove:{minArgs:1,maxArgs:1},set:{minArgs:1,maxArgs:1}}},tabs:{captureVisibleTab:{minArgs:0,maxArgs:2},create:{minArgs:1,maxArgs:1},detectLanguage:{minArgs:0,maxArgs:1},discard:{minArgs:0,maxArgs:1},duplicate:{minArgs:1,maxArgs:1},executeScript:{minArgs:1,maxArgs:2},get:{minArgs:1,maxArgs:1},getCurrent:{minArgs:0,maxArgs:0},getZoom:{minArgs:0,maxArgs:1},getZoomSettings:{minArgs:0,maxArgs:1},goBack:{minArgs:0,maxArgs:1},goForward:{minArgs:0,maxArgs:1},highlight:{minArgs:1,maxArgs:1},insertCSS:{minArgs:1,maxArgs:2},move:{minArgs:2,maxArgs:2},query:{minArgs:1,maxArgs:1},reload:{minArgs:0,maxArgs:2},remove:{minArgs:1,maxArgs:1},removeCSS:{minArgs:1,maxArgs:2},sendMessage:{minArgs:2,maxArgs:3},setZoom:{minArgs:1,maxArgs:2},setZoomSettings:{minArgs:1,maxArgs:2},update:{minArgs:1,maxArgs:2}},topSites:{get:{minArgs:0,maxArgs:0}},webNavigation:{getAllFrames:{minArgs:1,maxArgs:1},getFrame:{minArgs:1,maxArgs:1}},webRequest:{handlerBehaviorChanged:{minArgs:0,maxArgs:0}},windows:{create:{minArgs:0,maxArgs:1},get:{minArgs:1,maxArgs:2},getAll:{minArgs:0,maxArgs:1},getCurrent:{minArgs:0,maxArgs:1},getLastFocused:{minArgs:0,maxArgs:1},remove:{minArgs:1,maxArgs:1},update:{minArgs:2,maxArgs:2}}};if(Object.keys(u).length===0)throw new Error("api-metadata.json has not been included in browser-polyfill");class d extends WeakMap{constructor(T,k=void 0){super(k),this.createItem=T}get(T){return this.has(T)||this.set(T,this.createItem(T)),super.get(T)}}const f=v=>v&&typeof v=="object"&&typeof v.then=="function",w=(v,T)=>(...k)=>{c.runtime.lastError?v.reject(new Error(c.runtime.lastError.message)):T.singleCallbackArg||k.length<=1&&T.singleCallbackArg!==!1?v.resolve(k[0]):v.resolve(k)},g=v=>v==1?"argument":"arguments",E=(v,T)=>function(C,...m){if(m.length<T.minArgs)throw new Error(`Expected at least ${T.minArgs} ${g(T.minArgs)} for ${v}(), got ${m.length}`);if(m.length>T.maxArgs)throw new Error(`Expected at most ${T.maxArgs} ${g(T.maxArgs)} for ${v}(), got ${m.length}`);return new Promise((B,W)=>{if(T.fallbackToNoCallback)try{C[v](...m,w({resolve:B,reject:W},T))}catch(O){console.warn(`${v} API method doesn't seem to support the callback parameter, falling back to call it without a callback: `,O),C[v](...m),T.fallbackToNoCallback=!1,T.noCallback=!0,B()}else T.noCallback?(C[v](...m),B()):C[v](...m,w({resolve:B,reject:W},T))})},b=(v,T,k)=>new Proxy(T,{apply(C,m,B){return k.call(m,v,...B)}});let P=Function.call.bind(Object.prototype.hasOwnProperty);const F=(v,T={},k={})=>{let C=Object.create(null),m={has(W,O){return O in v||O in C},get(W,O,X){if(O in C)return C[O];if(!(O in v))return;let K=v[O];if(typeof K=="function")if(typeof T[O]=="function")K=b(v,v[O],T[O]);else if(P(k,O)){let ie=E(O,k[O]);K=b(v,v[O],ie)}else K=K.bind(v);else if(typeof K=="object"&&K!==null&&(P(T,O)||P(k,O)))K=F(K,T[O],k[O]);else if(P(k,"*"))K=F(K,T[O],k["*"]);else return Object.defineProperty(C,O,{configurable:!0,enumerable:!0,get(){return v[O]},set(ie){v[O]=ie}}),K;return C[O]=K,K},set(W,O,X,K){return O in C?C[O]=X:v[O]=X,!0},defineProperty(W,O,X){return Reflect.defineProperty(C,O,X)},deleteProperty(W,O){return Reflect.deleteProperty(C,O)}},B=Object.create(v);return new Proxy(B,m)},L=v=>({addListener(T,k,...C){T.addListener(v.get(k),...C)},hasListener(T,k){return T.hasListener(v.get(k))},removeListener(T,k){T.removeListener(v.get(k))}}),N=new d(v=>typeof v!="function"?v:function(k){const C=F(k,{},{getContent:{minArgs:0,maxArgs:0}});v(C)});let A=!1;const S=new d(v=>typeof v!="function"?v:function(k,C,m){let B=!1,W,O=new Promise(ke=>{W=function(te){A||(console.warn(i,new Error().stack),A=!0),B=!0,ke(te)}}),X;try{X=v(k,C,W)}catch(ke){X=Promise.reject(ke)}const K=X!==!0&&f(X);if(X!==!0&&!K&&!B)return!1;const ie=ke=>{ke.then(te=>{m(te)},te=>{let st;te&&(te instanceof Error||typeof te.message=="string")?st=te.message:st="An unexpected error occurred",m({__mozWebExtensionPolyfillReject__:!0,message:st})}).catch(te=>{console.error("Failed to send onMessage rejected reply",te)})};return ie(K?X:O),!0}),I=({reject:v,resolve:T},k)=>{c.runtime.lastError?c.runtime.lastError.message===n?T():v(new Error(c.runtime.lastError.message)):k&&k.__mozWebExtensionPolyfillReject__?v(new Error(k.message)):T(k)},D=(v,T,k,...C)=>{if(C.length<T.minArgs)throw new Error(`Expected at least ${T.minArgs} ${g(T.minArgs)} for ${v}(), got ${C.length}`);if(C.length>T.maxArgs)throw new Error(`Expected at most ${T.maxArgs} ${g(T.maxArgs)} for ${v}(), got ${C.length}`);return new Promise((m,B)=>{const W=I.bind(null,{resolve:m,reject:B});C.push(W),k.sendMessage(...C)})},R={devtools:{network:{onRequestFinished:L(N)}},runtime:{onMessage:L(S),onMessageExternal:L(S),sendMessage:D.bind(null,"sendMessage",{minArgs:1,maxArgs:3})},tabs:{sendMessage:D.bind(null,"sendMessage",{minArgs:2,maxArgs:3})}},x={clear:{minArgs:1,maxArgs:1},get:{minArgs:1,maxArgs:1},set:{minArgs:1,maxArgs:1}};return u.privacy={network:{"*":x},services:{"*":x},websites:{"*":x}},F(c,R,u)};r.exports=a(chrome)}else r.exports=globalThis.browser})})(Ci);const Gh=Ci.exports;var Go=Object.defineProperty,qo=Object.defineProperties,Ko=Object.getOwnPropertyDescriptors,En=Object.getOwnPropertySymbols,Jo=Object.prototype.hasOwnProperty,Yo=Object.prototype.propertyIsEnumerable,Tn=(t,e,r)=>e in t?Go(t,e,{enumerable:!0,configurable:!0,writable:!0,value:r}):t[e]=r,Pi=(t,e)=>{for(var r in e||(e={}))Jo.call(e,r)&&Tn(t,r,e[r]);if(En)for(var r of En(e))Yo.call(e,r)&&Tn(t,r,e[r]);return t},Ri=(t,e)=>qo(t,Ko(e)),Xo=/^((?:background$)|devtools|popup|options|content-script|window)(?:@(\d+)(?:\.(\d+))?)?$/,Oi=t=>{const[,e,r,n]=t.match(Xo)||[];return{context:e,tabId:+r,frameId:n?+n:void 0}},qh=({context:t,tabId:e,frameId:r})=>["background","popup","options"].includes(t)?t:`${t}@${e}${r?`.${r}`:""}`,Ni={};(function(t){Object.defineProperties(t,{__esModule:{value:!0},[Symbol.toStringTag]:{value:"Module"}});var e={},r={};r.byteLength=w,r.toByteArray=E,r.fromByteArray=F;for(var n=[],i=[],a=typeof Uint8Array<"u"?Uint8Array:Array,c="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",u=0,d=c.length;u<d;++u)n[u]=c[u],i[c.charCodeAt(u)]=u;i["-".charCodeAt(0)]=62,i["_".charCodeAt(0)]=63;function f(A){var S=A.length;if(S%4>0)throw new Error("Invalid string. Length must be a multiple of 4");var I=A.indexOf("=");I===-1&&(I=S);var D=I===S?0:4-I%4;return[I,D]}function w(A){var S=f(A),I=S[0],D=S[1];return(I+D)*3/4-D}function g(A,S,I){return(S+I)*3/4-I}function E(A){var S,I=f(A),D=I[0],R=I[1],x=new a(g(A,D,R)),v=0,T=R>0?D-4:D,k;for(k=0;k<T;k+=4)S=i[A.charCodeAt(k)]<<18|i[A.charCodeAt(k+1)]<<12|i[A.charCodeAt(k+2)]<<6|i[A.charCodeAt(k+3)],x[v++]=S>>16&255,x[v++]=S>>8&255,x[v++]=S&255;return R===2&&(S=i[A.charCodeAt(k)]<<2|i[A.charCodeAt(k+1)]>>4,x[v++]=S&255),R===1&&(S=i[A.charCodeAt(k)]<<10|i[A.charCodeAt(k+1)]<<4|i[A.charCodeAt(k+2)]>>2,x[v++]=S>>8&255,x[v++]=S&255),x}function b(A){return n[A>>18&63]+n[A>>12&63]+n[A>>6&63]+n[A&63]}function P(A,S,I){for(var D,R=[],x=S;x<I;x+=3)D=(A[x]<<16&16711680)+(A[x+1]<<8&65280)+(A[x+2]&255),R.push(b(D));return R.join("")}function F(A){for(var S,I=A.length,D=I%3,R=[],x=16383,v=0,T=I-D;v<T;v+=x)R.push(P(A,v,v+x>T?T:v+x));return D===1?(S=A[I-1],R.push(n[S>>2]+n[S<<4&63]+"==")):D===2&&(S=(A[I-2]<<8)+A[I-1],R.push(n[S>>10]+n[S>>4&63]+n[S<<2&63]+"=")),R.join("")}var L={};/*! ieee754. BSD-3-Clause License. Feross Aboukhadijeh <https://feross.org/opensource> */L.read=function(A,S,I,D,R){var x,v,T=R*8-D-1,k=(1<<T)-1,C=k>>1,m=-7,B=I?R-1:0,W=I?-1:1,O=A[S+B];for(B+=W,x=O&(1<<-m)-1,O>>=-m,m+=T;m>0;x=x*256+A[S+B],B+=W,m-=8);for(v=x&(1<<-m)-1,x>>=-m,m+=D;m>0;v=v*256+A[S+B],B+=W,m-=8);if(x===0)x=1-C;else{if(x===k)return v?NaN:(O?-1:1)*(1/0);v=v+Math.pow(2,D),x=x-C}return(O?-1:1)*v*Math.pow(2,x-D)},L.write=function(A,S,I,D,R,x){var v,T,k,C=x*8-R-1,m=(1<<C)-1,B=m>>1,W=R===23?Math.pow(2,-24)-Math.pow(2,-77):0,O=D?0:x-1,X=D?1:-1,K=S<0||S===0&&1/S<0?1:0;for(S=Math.abs(S),isNaN(S)||S===1/0?(T=isNaN(S)?1:0,v=m):(v=Math.floor(Math.log(S)/Math.LN2),S*(k=Math.pow(2,-v))<1&&(v--,k*=2),v+B>=1?S+=W/k:S+=W*Math.pow(2,1-B),S*k>=2&&(v++,k/=2),v+B>=m?(T=0,v=m):v+B>=1?(T=(S*k-1)*Math.pow(2,R),v=v+B):(T=S*Math.pow(2,B-1)*Math.pow(2,R),v=0));R>=8;A[I+O]=T&255,O+=X,T/=256,R-=8);for(v=v<<R|T,C+=R;C>0;A[I+O]=v&255,O+=X,v/=256,C-=8);A[I+O-X]|=K*128};/*!
 * The buffer module from node.js, for the browser.
 *
 * @author   Feross Aboukhadijeh <https://feross.org>
 * @license  MIT
 */(function(A){const S=r,I=L,D=typeof Symbol=="function"&&typeof Symbol.for=="function"?Symbol.for("nodejs.util.inspect.custom"):null;A.Buffer=m,A.SlowBuffer=Js,A.INSPECT_MAX_BYTES=50;const R=2147483647;A.kMaxLength=R;const{Uint8Array:x,ArrayBuffer:v,SharedArrayBuffer:T}=globalThis;m.TYPED_ARRAY_SUPPORT=k(),!m.TYPED_ARRAY_SUPPORT&&typeof console<"u"&&typeof console.error=="function"&&console.error("This browser lacks typed array (Uint8Array) support which is required by `buffer` v5.x. Use `buffer` v4.x if you require old browser support.");function k(){try{const l=new x(1),s={foo:function(){return 42}};return Object.setPrototypeOf(s,x.prototype),Object.setPrototypeOf(l,s),l.foo()===42}catch{return!1}}Object.defineProperty(m.prototype,"parent",{enumerable:!0,get:function(){if(!!m.isBuffer(this))return this.buffer}}),Object.defineProperty(m.prototype,"offset",{enumerable:!0,get:function(){if(!!m.isBuffer(this))return this.byteOffset}});function C(l){if(l>R)throw new RangeError('The value "'+l+'" is invalid for option "size"');const s=new x(l);return Object.setPrototypeOf(s,m.prototype),s}function m(l,s,o){if(typeof l=="number"){if(typeof s=="string")throw new TypeError('The "string" argument must be of type string. Received type number');return X(l)}return B(l,s,o)}m.poolSize=8192;function B(l,s,o){if(typeof l=="string")return K(l,s);if(v.isView(l))return ke(l);if(l==null)throw new TypeError("The first argument must be one of type string, Buffer, ArrayBuffer, Array, or Array-like Object. Received type "+typeof l);if(se(l,v)||l&&se(l.buffer,v)||typeof T<"u"&&(se(l,T)||l&&se(l.buffer,T)))return te(l,s,o);if(typeof l=="number")throw new TypeError('The "value" argument must not be of type number. Received type number');const h=l.valueOf&&l.valueOf();if(h!=null&&h!==l)return m.from(h,s,o);const p=st(l);if(p)return p;if(typeof Symbol<"u"&&Symbol.toPrimitive!=null&&typeof l[Symbol.toPrimitive]=="function")return m.from(l[Symbol.toPrimitive]("string"),s,o);throw new TypeError("The first argument must be one of type string, Buffer, ArrayBuffer, Array, or Array-like Object. Received type "+typeof l)}m.from=function(l,s,o){return B(l,s,o)},Object.setPrototypeOf(m.prototype,x.prototype),Object.setPrototypeOf(m,x);function W(l){if(typeof l!="number")throw new TypeError('"size" argument must be of type number');if(l<0)throw new RangeError('The value "'+l+'" is invalid for option "size"')}function O(l,s,o){return W(l),l<=0?C(l):s!==void 0?typeof o=="string"?C(l).fill(s,o):C(l).fill(s):C(l)}m.alloc=function(l,s,o){return O(l,s,o)};function X(l){return W(l),C(l<0?0:Zt(l)|0)}m.allocUnsafe=function(l){return X(l)},m.allocUnsafeSlow=function(l){return X(l)};function K(l,s){if((typeof s!="string"||s==="")&&(s="utf8"),!m.isEncoding(s))throw new TypeError("Unknown encoding: "+s);const o=Xr(l,s)|0;let h=C(o);const p=h.write(l,s);return p!==o&&(h=h.slice(0,p)),h}function ie(l){const s=l.length<0?0:Zt(l.length)|0,o=C(s);for(let h=0;h<s;h+=1)o[h]=l[h]&255;return o}function ke(l){if(se(l,x)){const s=new x(l);return te(s.buffer,s.byteOffset,s.byteLength)}return ie(l)}function te(l,s,o){if(s<0||l.byteLength<s)throw new RangeError('"offset" is outside of buffer bounds');if(l.byteLength<s+(o||0))throw new RangeError('"length" is outside of buffer bounds');let h;return s===void 0&&o===void 0?h=new x(l):o===void 0?h=new x(l,s):h=new x(l,s,o),Object.setPrototypeOf(h,m.prototype),h}function st(l){if(m.isBuffer(l)){const s=Zt(l.length)|0,o=C(s);return o.length===0||l.copy(o,0,0,s),o}if(l.length!==void 0)return typeof l.length!="number"||tr(l.length)?C(0):ie(l);if(l.type==="Buffer"&&Array.isArray(l.data))return ie(l.data)}function Zt(l){if(l>=R)throw new RangeError("Attempt to allocate Buffer larger than maximum size: 0x"+R.toString(16)+" bytes");return l|0}function Js(l){return+l!=l&&(l=0),m.alloc(+l)}m.isBuffer=function(s){return s!=null&&s._isBuffer===!0&&s!==m.prototype},m.compare=function(s,o){if(se(s,x)&&(s=m.from(s,s.offset,s.byteLength)),se(o,x)&&(o=m.from(o,o.offset,o.byteLength)),!m.isBuffer(s)||!m.isBuffer(o))throw new TypeError('The "buf1", "buf2" arguments must be one of type Buffer or Uint8Array');if(s===o)return 0;let h=s.length,p=o.length;for(let _=0,y=Math.min(h,p);_<y;++_)if(s[_]!==o[_]){h=s[_],p=o[_];break}return h<p?-1:p<h?1:0},m.isEncoding=function(s){switch(String(s).toLowerCase()){case"hex":case"utf8":case"utf-8":case"ascii":case"latin1":case"binary":case"base64":case"ucs2":case"ucs-2":case"utf16le":case"utf-16le":return!0;default:return!1}},m.concat=function(s,o){if(!Array.isArray(s))throw new TypeError('"list" argument must be an Array of Buffers');if(s.length===0)return m.alloc(0);let h;if(o===void 0)for(o=0,h=0;h<s.length;++h)o+=s[h].length;const p=m.allocUnsafe(o);let _=0;for(h=0;h<s.length;++h){let y=s[h];if(se(y,x))_+y.length>p.length?(m.isBuffer(y)||(y=m.from(y)),y.copy(p,_)):x.prototype.set.call(p,y,_);else if(m.isBuffer(y))y.copy(p,_);else throw new TypeError('"list" argument must be an Array of Buffers');_+=y.length}return p};function Xr(l,s){if(m.isBuffer(l))return l.length;if(v.isView(l)||se(l,v))return l.byteLength;if(typeof l!="string")throw new TypeError('The "string" argument must be one of type string, Buffer, or ArrayBuffer. Received type '+typeof l);const o=l.length,h=arguments.length>2&&arguments[2]===!0;if(!h&&o===0)return 0;let p=!1;for(;;)switch(s){case"ascii":case"latin1":case"binary":return o;case"utf8":case"utf-8":return er(l).length;case"ucs2":case"ucs-2":case"utf16le":case"utf-16le":return o*2;case"hex":return o>>>1;case"base64":return un(l).length;default:if(p)return h?-1:er(l).length;s=(""+s).toLowerCase(),p=!0}}m.byteLength=Xr;function Ys(l,s,o){let h=!1;if((s===void 0||s<0)&&(s=0),s>this.length||((o===void 0||o>this.length)&&(o=this.length),o<=0)||(o>>>=0,s>>>=0,o<=s))return"";for(l||(l="utf8");;)switch(l){case"hex":return oo(this,s,o);case"utf8":case"utf-8":return en(this,s,o);case"ascii":return io(this,s,o);case"latin1":case"binary":return so(this,s,o);case"base64":return ro(this,s,o);case"ucs2":case"ucs-2":case"utf16le":case"utf-16le":return ao(this,s,o);default:if(h)throw new TypeError("Unknown encoding: "+l);l=(l+"").toLowerCase(),h=!0}}m.prototype._isBuffer=!0;function xe(l,s,o){const h=l[s];l[s]=l[o],l[o]=h}m.prototype.swap16=function(){const s=this.length;if(s%2!==0)throw new RangeError("Buffer size must be a multiple of 16-bits");for(let o=0;o<s;o+=2)xe(this,o,o+1);return this},m.prototype.swap32=function(){const s=this.length;if(s%4!==0)throw new RangeError("Buffer size must be a multiple of 32-bits");for(let o=0;o<s;o+=4)xe(this,o,o+3),xe(this,o+1,o+2);return this},m.prototype.swap64=function(){const s=this.length;if(s%8!==0)throw new RangeError("Buffer size must be a multiple of 64-bits");for(let o=0;o<s;o+=8)xe(this,o,o+7),xe(this,o+1,o+6),xe(this,o+2,o+5),xe(this,o+3,o+4);return this},m.prototype.toString=function(){const s=this.length;return s===0?"":arguments.length===0?en(this,0,s):Ys.apply(this,arguments)},m.prototype.toLocaleString=m.prototype.toString,m.prototype.equals=function(s){if(!m.isBuffer(s))throw new TypeError("Argument must be a Buffer");return this===s?!0:m.compare(this,s)===0},m.prototype.inspect=function(){let s="";const o=A.INSPECT_MAX_BYTES;return s=this.toString("hex",0,o).replace(/(.{2})/g,"$1 ").trim(),this.length>o&&(s+=" ... "),"<Buffer "+s+">"},D&&(m.prototype[D]=m.prototype.inspect),m.prototype.compare=function(s,o,h,p,_){if(se(s,x)&&(s=m.from(s,s.offset,s.byteLength)),!m.isBuffer(s))throw new TypeError('The "target" argument must be one of type Buffer or Uint8Array. Received type '+typeof s);if(o===void 0&&(o=0),h===void 0&&(h=s?s.length:0),p===void 0&&(p=0),_===void 0&&(_=this.length),o<0||h>s.length||p<0||_>this.length)throw new RangeError("out of range index");if(p>=_&&o>=h)return 0;if(p>=_)return-1;if(o>=h)return 1;if(o>>>=0,h>>>=0,p>>>=0,_>>>=0,this===s)return 0;let y=_-p,U=h-o;const J=Math.min(y,U),G=this.slice(p,_),Y=s.slice(o,h);for(let H=0;H<J;++H)if(G[H]!==Y[H]){y=G[H],U=Y[H];break}return y<U?-1:U<y?1:0};function Zr(l,s,o,h,p){if(l.length===0)return-1;if(typeof o=="string"?(h=o,o=0):o>2147483647?o=2147483647:o<-2147483648&&(o=-2147483648),o=+o,tr(o)&&(o=p?0:l.length-1),o<0&&(o=l.length+o),o>=l.length){if(p)return-1;o=l.length-1}else if(o<0)if(p)o=0;else return-1;if(typeof s=="string"&&(s=m.from(s,h)),m.isBuffer(s))return s.length===0?-1:Qr(l,s,o,h,p);if(typeof s=="number")return s=s&255,typeof x.prototype.indexOf=="function"?p?x.prototype.indexOf.call(l,s,o):x.prototype.lastIndexOf.call(l,s,o):Qr(l,[s],o,h,p);throw new TypeError("val must be string, number or Buffer")}function Qr(l,s,o,h,p){let _=1,y=l.length,U=s.length;if(h!==void 0&&(h=String(h).toLowerCase(),h==="ucs2"||h==="ucs-2"||h==="utf16le"||h==="utf-16le")){if(l.length<2||s.length<2)return-1;_=2,y/=2,U/=2,o/=2}function J(Y,H){return _===1?Y[H]:Y.readUInt16BE(H*_)}let G;if(p){let Y=-1;for(G=o;G<y;G++)if(J(l,G)===J(s,Y===-1?0:G-Y)){if(Y===-1&&(Y=G),G-Y+1===U)return Y*_}else Y!==-1&&(G-=G-Y),Y=-1}else for(o+U>y&&(o=y-U),G=o;G>=0;G--){let Y=!0;for(let H=0;H<U;H++)if(J(l,G+H)!==J(s,H)){Y=!1;break}if(Y)return G}return-1}m.prototype.includes=function(s,o,h){return this.indexOf(s,o,h)!==-1},m.prototype.indexOf=function(s,o,h){return Zr(this,s,o,h,!0)},m.prototype.lastIndexOf=function(s,o,h){return Zr(this,s,o,h,!1)};function Xs(l,s,o,h){o=Number(o)||0;const p=l.length-o;h?(h=Number(h),h>p&&(h=p)):h=p;const _=s.length;h>_/2&&(h=_/2);let y;for(y=0;y<h;++y){const U=parseInt(s.substr(y*2,2),16);if(tr(U))return y;l[o+y]=U}return y}function Zs(l,s,o,h){return St(er(s,l.length-o),l,o,h)}function Qs(l,s,o,h){return St(ho(s),l,o,h)}function eo(l,s,o,h){return St(un(s),l,o,h)}function to(l,s,o,h){return St(fo(s,l.length-o),l,o,h)}m.prototype.write=function(s,o,h,p){if(o===void 0)p="utf8",h=this.length,o=0;else if(h===void 0&&typeof o=="string")p=o,h=this.length,o=0;else if(isFinite(o))o=o>>>0,isFinite(h)?(h=h>>>0,p===void 0&&(p="utf8")):(p=h,h=void 0);else throw new Error("Buffer.write(string, encoding, offset[, length]) is no longer supported");const _=this.length-o;if((h===void 0||h>_)&&(h=_),s.length>0&&(h<0||o<0)||o>this.length)throw new RangeError("Attempt to write outside buffer bounds");p||(p="utf8");let y=!1;for(;;)switch(p){case"hex":return Xs(this,s,o,h);case"utf8":case"utf-8":return Zs(this,s,o,h);case"ascii":case"latin1":case"binary":return Qs(this,s,o,h);case"base64":return eo(this,s,o,h);case"ucs2":case"ucs-2":case"utf16le":case"utf-16le":return to(this,s,o,h);default:if(y)throw new TypeError("Unknown encoding: "+p);p=(""+p).toLowerCase(),y=!0}},m.prototype.toJSON=function(){return{type:"Buffer",data:Array.prototype.slice.call(this._arr||this,0)}};function ro(l,s,o){return s===0&&o===l.length?S.fromByteArray(l):S.fromByteArray(l.slice(s,o))}function en(l,s,o){o=Math.min(l.length,o);const h=[];let p=s;for(;p<o;){const _=l[p];let y=null,U=_>239?4:_>223?3:_>191?2:1;if(p+U<=o){let J,G,Y,H;switch(U){case 1:_<128&&(y=_);break;case 2:J=l[p+1],(J&192)===128&&(H=(_&31)<<6|J&63,H>127&&(y=H));break;case 3:J=l[p+1],G=l[p+2],(J&192)===128&&(G&192)===128&&(H=(_&15)<<12|(J&63)<<6|G&63,H>2047&&(H<55296||H>57343)&&(y=H));break;case 4:J=l[p+1],G=l[p+2],Y=l[p+3],(J&192)===128&&(G&192)===128&&(Y&192)===128&&(H=(_&15)<<18|(J&63)<<12|(G&63)<<6|Y&63,H>65535&&H<1114112&&(y=H))}}y===null?(y=65533,U=1):y>65535&&(y-=65536,h.push(y>>>10&1023|55296),y=56320|y&1023),h.push(y),p+=U}return no(h)}const tn=4096;function no(l){const s=l.length;if(s<=tn)return String.fromCharCode.apply(String,l);let o="",h=0;for(;h<s;)o+=String.fromCharCode.apply(String,l.slice(h,h+=tn));return o}function io(l,s,o){let h="";o=Math.min(l.length,o);for(let p=s;p<o;++p)h+=String.fromCharCode(l[p]&127);return h}function so(l,s,o){let h="";o=Math.min(l.length,o);for(let p=s;p<o;++p)h+=String.fromCharCode(l[p]);return h}function oo(l,s,o){const h=l.length;(!s||s<0)&&(s=0),(!o||o<0||o>h)&&(o=h);let p="";for(let _=s;_<o;++_)p+=po[l[_]];return p}function ao(l,s,o){const h=l.slice(s,o);let p="";for(let _=0;_<h.length-1;_+=2)p+=String.fromCharCode(h[_]+h[_+1]*256);return p}m.prototype.slice=function(s,o){const h=this.length;s=~~s,o=o===void 0?h:~~o,s<0?(s+=h,s<0&&(s=0)):s>h&&(s=h),o<0?(o+=h,o<0&&(o=0)):o>h&&(o=h),o<s&&(o=s);const p=this.subarray(s,o);return Object.setPrototypeOf(p,m.prototype),p};function Z(l,s,o){if(l%1!==0||l<0)throw new RangeError("offset is not uint");if(l+s>o)throw new RangeError("Trying to access beyond buffer length")}m.prototype.readUintLE=m.prototype.readUIntLE=function(s,o,h){s=s>>>0,o=o>>>0,h||Z(s,o,this.length);let p=this[s],_=1,y=0;for(;++y<o&&(_*=256);)p+=this[s+y]*_;return p},m.prototype.readUintBE=m.prototype.readUIntBE=function(s,o,h){s=s>>>0,o=o>>>0,h||Z(s,o,this.length);let p=this[s+--o],_=1;for(;o>0&&(_*=256);)p+=this[s+--o]*_;return p},m.prototype.readUint8=m.prototype.readUInt8=function(s,o){return s=s>>>0,o||Z(s,1,this.length),this[s]},m.prototype.readUint16LE=m.prototype.readUInt16LE=function(s,o){return s=s>>>0,o||Z(s,2,this.length),this[s]|this[s+1]<<8},m.prototype.readUint16BE=m.prototype.readUInt16BE=function(s,o){return s=s>>>0,o||Z(s,2,this.length),this[s]<<8|this[s+1]},m.prototype.readUint32LE=m.prototype.readUInt32LE=function(s,o){return s=s>>>0,o||Z(s,4,this.length),(this[s]|this[s+1]<<8|this[s+2]<<16)+this[s+3]*16777216},m.prototype.readUint32BE=m.prototype.readUInt32BE=function(s,o){return s=s>>>0,o||Z(s,4,this.length),this[s]*16777216+(this[s+1]<<16|this[s+2]<<8|this[s+3])},m.prototype.readBigUInt64LE=me(function(s){s=s>>>0,Be(s,"offset");const o=this[s],h=this[s+7];(o===void 0||h===void 0)&&ot(s,this.length-8);const p=o+this[++s]*2**8+this[++s]*2**16+this[++s]*2**24,_=this[++s]+this[++s]*2**8+this[++s]*2**16+h*2**24;return BigInt(p)+(BigInt(_)<<BigInt(32))}),m.prototype.readBigUInt64BE=me(function(s){s=s>>>0,Be(s,"offset");const o=this[s],h=this[s+7];(o===void 0||h===void 0)&&ot(s,this.length-8);const p=o*2**24+this[++s]*2**16+this[++s]*2**8+this[++s],_=this[++s]*2**24+this[++s]*2**16+this[++s]*2**8+h;return(BigInt(p)<<BigInt(32))+BigInt(_)}),m.prototype.readIntLE=function(s,o,h){s=s>>>0,o=o>>>0,h||Z(s,o,this.length);let p=this[s],_=1,y=0;for(;++y<o&&(_*=256);)p+=this[s+y]*_;return _*=128,p>=_&&(p-=Math.pow(2,8*o)),p},m.prototype.readIntBE=function(s,o,h){s=s>>>0,o=o>>>0,h||Z(s,o,this.length);let p=o,_=1,y=this[s+--p];for(;p>0&&(_*=256);)y+=this[s+--p]*_;return _*=128,y>=_&&(y-=Math.pow(2,8*o)),y},m.prototype.readInt8=function(s,o){return s=s>>>0,o||Z(s,1,this.length),this[s]&128?(255-this[s]+1)*-1:this[s]},m.prototype.readInt16LE=function(s,o){s=s>>>0,o||Z(s,2,this.length);const h=this[s]|this[s+1]<<8;return h&32768?h|4294901760:h},m.prototype.readInt16BE=function(s,o){s=s>>>0,o||Z(s,2,this.length);const h=this[s+1]|this[s]<<8;return h&32768?h|4294901760:h},m.prototype.readInt32LE=function(s,o){return s=s>>>0,o||Z(s,4,this.length),this[s]|this[s+1]<<8|this[s+2]<<16|this[s+3]<<24},m.prototype.readInt32BE=function(s,o){return s=s>>>0,o||Z(s,4,this.length),this[s]<<24|this[s+1]<<16|this[s+2]<<8|this[s+3]},m.prototype.readBigInt64LE=me(function(s){s=s>>>0,Be(s,"offset");const o=this[s],h=this[s+7];(o===void 0||h===void 0)&&ot(s,this.length-8);const p=this[s+4]+this[s+5]*2**8+this[s+6]*2**16+(h<<24);return(BigInt(p)<<BigInt(32))+BigInt(o+this[++s]*2**8+this[++s]*2**16+this[++s]*2**24)}),m.prototype.readBigInt64BE=me(function(s){s=s>>>0,Be(s,"offset");const o=this[s],h=this[s+7];(o===void 0||h===void 0)&&ot(s,this.length-8);const p=(o<<24)+this[++s]*2**16+this[++s]*2**8+this[++s];return(BigInt(p)<<BigInt(32))+BigInt(this[++s]*2**24+this[++s]*2**16+this[++s]*2**8+h)}),m.prototype.readFloatLE=function(s,o){return s=s>>>0,o||Z(s,4,this.length),I.read(this,s,!0,23,4)},m.prototype.readFloatBE=function(s,o){return s=s>>>0,o||Z(s,4,this.length),I.read(this,s,!1,23,4)},m.prototype.readDoubleLE=function(s,o){return s=s>>>0,o||Z(s,8,this.length),I.read(this,s,!0,52,8)},m.prototype.readDoubleBE=function(s,o){return s=s>>>0,o||Z(s,8,this.length),I.read(this,s,!1,52,8)};function re(l,s,o,h,p,_){if(!m.isBuffer(l))throw new TypeError('"buffer" argument must be a Buffer instance');if(s>p||s<_)throw new RangeError('"value" argument is out of bounds');if(o+h>l.length)throw new RangeError("Index out of range")}m.prototype.writeUintLE=m.prototype.writeUIntLE=function(s,o,h,p){if(s=+s,o=o>>>0,h=h>>>0,!p){const U=Math.pow(2,8*h)-1;re(this,s,o,h,U,0)}let _=1,y=0;for(this[o]=s&255;++y<h&&(_*=256);)this[o+y]=s/_&255;return o+h},m.prototype.writeUintBE=m.prototype.writeUIntBE=function(s,o,h,p){if(s=+s,o=o>>>0,h=h>>>0,!p){const U=Math.pow(2,8*h)-1;re(this,s,o,h,U,0)}let _=h-1,y=1;for(this[o+_]=s&255;--_>=0&&(y*=256);)this[o+_]=s/y&255;return o+h},m.prototype.writeUint8=m.prototype.writeUInt8=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,1,255,0),this[o]=s&255,o+1},m.prototype.writeUint16LE=m.prototype.writeUInt16LE=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,2,65535,0),this[o]=s&255,this[o+1]=s>>>8,o+2},m.prototype.writeUint16BE=m.prototype.writeUInt16BE=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,2,65535,0),this[o]=s>>>8,this[o+1]=s&255,o+2},m.prototype.writeUint32LE=m.prototype.writeUInt32LE=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,4,4294967295,0),this[o+3]=s>>>24,this[o+2]=s>>>16,this[o+1]=s>>>8,this[o]=s&255,o+4},m.prototype.writeUint32BE=m.prototype.writeUInt32BE=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,4,4294967295,0),this[o]=s>>>24,this[o+1]=s>>>16,this[o+2]=s>>>8,this[o+3]=s&255,o+4};function rn(l,s,o,h,p){ln(s,h,p,l,o,7);let _=Number(s&BigInt(4294967295));l[o++]=_,_=_>>8,l[o++]=_,_=_>>8,l[o++]=_,_=_>>8,l[o++]=_;let y=Number(s>>BigInt(32)&BigInt(4294967295));return l[o++]=y,y=y>>8,l[o++]=y,y=y>>8,l[o++]=y,y=y>>8,l[o++]=y,o}function nn(l,s,o,h,p){ln(s,h,p,l,o,7);let _=Number(s&BigInt(4294967295));l[o+7]=_,_=_>>8,l[o+6]=_,_=_>>8,l[o+5]=_,_=_>>8,l[o+4]=_;let y=Number(s>>BigInt(32)&BigInt(4294967295));return l[o+3]=y,y=y>>8,l[o+2]=y,y=y>>8,l[o+1]=y,y=y>>8,l[o]=y,o+8}m.prototype.writeBigUInt64LE=me(function(s,o=0){return rn(this,s,o,BigInt(0),BigInt("0xffffffffffffffff"))}),m.prototype.writeBigUInt64BE=me(function(s,o=0){return nn(this,s,o,BigInt(0),BigInt("0xffffffffffffffff"))}),m.prototype.writeIntLE=function(s,o,h,p){if(s=+s,o=o>>>0,!p){const J=Math.pow(2,8*h-1);re(this,s,o,h,J-1,-J)}let _=0,y=1,U=0;for(this[o]=s&255;++_<h&&(y*=256);)s<0&&U===0&&this[o+_-1]!==0&&(U=1),this[o+_]=(s/y>>0)-U&255;return o+h},m.prototype.writeIntBE=function(s,o,h,p){if(s=+s,o=o>>>0,!p){const J=Math.pow(2,8*h-1);re(this,s,o,h,J-1,-J)}let _=h-1,y=1,U=0;for(this[o+_]=s&255;--_>=0&&(y*=256);)s<0&&U===0&&this[o+_+1]!==0&&(U=1),this[o+_]=(s/y>>0)-U&255;return o+h},m.prototype.writeInt8=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,1,127,-128),s<0&&(s=255+s+1),this[o]=s&255,o+1},m.prototype.writeInt16LE=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,2,32767,-32768),this[o]=s&255,this[o+1]=s>>>8,o+2},m.prototype.writeInt16BE=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,2,32767,-32768),this[o]=s>>>8,this[o+1]=s&255,o+2},m.prototype.writeInt32LE=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,4,2147483647,-2147483648),this[o]=s&255,this[o+1]=s>>>8,this[o+2]=s>>>16,this[o+3]=s>>>24,o+4},m.prototype.writeInt32BE=function(s,o,h){return s=+s,o=o>>>0,h||re(this,s,o,4,2147483647,-2147483648),s<0&&(s=4294967295+s+1),this[o]=s>>>24,this[o+1]=s>>>16,this[o+2]=s>>>8,this[o+3]=s&255,o+4},m.prototype.writeBigInt64LE=me(function(s,o=0){return rn(this,s,o,-BigInt("0x8000000000000000"),BigInt("0x7fffffffffffffff"))}),m.prototype.writeBigInt64BE=me(function(s,o=0){return nn(this,s,o,-BigInt("0x8000000000000000"),BigInt("0x7fffffffffffffff"))});function sn(l,s,o,h,p,_){if(o+h>l.length)throw new RangeError("Index out of range");if(o<0)throw new RangeError("Index out of range")}function on(l,s,o,h,p){return s=+s,o=o>>>0,p||sn(l,s,o,4),I.write(l,s,o,h,23,4),o+4}m.prototype.writeFloatLE=function(s,o,h){return on(this,s,o,!0,h)},m.prototype.writeFloatBE=function(s,o,h){return on(this,s,o,!1,h)};function an(l,s,o,h,p){return s=+s,o=o>>>0,p||sn(l,s,o,8),I.write(l,s,o,h,52,8),o+8}m.prototype.writeDoubleLE=function(s,o,h){return an(this,s,o,!0,h)},m.prototype.writeDoubleBE=function(s,o,h){return an(this,s,o,!1,h)},m.prototype.copy=function(s,o,h,p){if(!m.isBuffer(s))throw new TypeError("argument should be a Buffer");if(h||(h=0),!p&&p!==0&&(p=this.length),o>=s.length&&(o=s.length),o||(o=0),p>0&&p<h&&(p=h),p===h||s.length===0||this.length===0)return 0;if(o<0)throw new RangeError("targetStart out of bounds");if(h<0||h>=this.length)throw new RangeError("Index out of range");if(p<0)throw new RangeError("sourceEnd out of bounds");p>this.length&&(p=this.length),s.length-o<p-h&&(p=s.length-o+h);const _=p-h;return this===s&&typeof x.prototype.copyWithin=="function"?this.copyWithin(o,h,p):x.prototype.set.call(s,this.subarray(h,p),o),_},m.prototype.fill=function(s,o,h,p){if(typeof s=="string"){if(typeof o=="string"?(p=o,o=0,h=this.length):typeof h=="string"&&(p=h,h=this.length),p!==void 0&&typeof p!="string")throw new TypeError("encoding must be a string");if(typeof p=="string"&&!m.isEncoding(p))throw new TypeError("Unknown encoding: "+p);if(s.length===1){const y=s.charCodeAt(0);(p==="utf8"&&y<128||p==="latin1")&&(s=y)}}else typeof s=="number"?s=s&255:typeof s=="boolean"&&(s=Number(s));if(o<0||this.length<o||this.length<h)throw new RangeError("Out of range index");if(h<=o)return this;o=o>>>0,h=h===void 0?this.length:h>>>0,s||(s=0);let _;if(typeof s=="number")for(_=o;_<h;++_)this[_]=s;else{const y=m.isBuffer(s)?s:m.from(s,p),U=y.length;if(U===0)throw new TypeError('The value "'+s+'" is invalid for argument "value"');for(_=0;_<h-o;++_)this[_+o]=y[_%U]}return this};const Ue={};function Qt(l,s,o){Ue[l]=class extends o{constructor(){super(),Object.defineProperty(this,"message",{value:s.apply(this,arguments),writable:!0,configurable:!0}),this.name=`${this.name} [${l}]`,this.stack,delete this.name}get code(){return l}set code(p){Object.defineProperty(this,"code",{configurable:!0,enumerable:!0,value:p,writable:!0})}toString(){return`${this.name} [${l}]: ${this.message}`}}}Qt("ERR_BUFFER_OUT_OF_BOUNDS",function(l){return l?`${l} is outside of buffer bounds`:"Attempt to access memory outside buffer bounds"},RangeError),Qt("ERR_INVALID_ARG_TYPE",function(l,s){return`The "${l}" argument must be of type number. Received type ${typeof s}`},TypeError),Qt("ERR_OUT_OF_RANGE",function(l,s,o){let h=`The value of "${l}" is out of range.`,p=o;return Number.isInteger(o)&&Math.abs(o)>2**32?p=cn(String(o)):typeof o=="bigint"&&(p=String(o),(o>BigInt(2)**BigInt(32)||o<-(BigInt(2)**BigInt(32)))&&(p=cn(p)),p+="n"),h+=` It must be ${s}. Received ${p}`,h},RangeError);function cn(l){let s="",o=l.length;const h=l[0]==="-"?1:0;for(;o>=h+4;o-=3)s=`_${l.slice(o-3,o)}${s}`;return`${l.slice(0,o)}${s}`}function co(l,s,o){Be(s,"offset"),(l[s]===void 0||l[s+o]===void 0)&&ot(s,l.length-(o+1))}function ln(l,s,o,h,p,_){if(l>o||l<s){const y=typeof s=="bigint"?"n":"";let U;throw _>3?s===0||s===BigInt(0)?U=`>= 0${y} and < 2${y} ** ${(_+1)*8}${y}`:U=`>= -(2${y} ** ${(_+1)*8-1}${y}) and < 2 ** ${(_+1)*8-1}${y}`:U=`>= ${s}${y} and <= ${o}${y}`,new Ue.ERR_OUT_OF_RANGE("value",U,l)}co(h,p,_)}function Be(l,s){if(typeof l!="number")throw new Ue.ERR_INVALID_ARG_TYPE(s,"number",l)}function ot(l,s,o){throw Math.floor(l)!==l?(Be(l,o),new Ue.ERR_OUT_OF_RANGE(o||"offset","an integer",l)):s<0?new Ue.ERR_BUFFER_OUT_OF_BOUNDS:new Ue.ERR_OUT_OF_RANGE(o||"offset",`>= ${o?1:0} and <= ${s}`,l)}const lo=/[^+/0-9A-Za-z-_]/g;function uo(l){if(l=l.split("=")[0],l=l.trim().replace(lo,""),l.length<2)return"";for(;l.length%4!==0;)l=l+"=";return l}function er(l,s){s=s||1/0;let o;const h=l.length;let p=null;const _=[];for(let y=0;y<h;++y){if(o=l.charCodeAt(y),o>55295&&o<57344){if(!p){if(o>56319){(s-=3)>-1&&_.push(239,191,189);continue}else if(y+1===h){(s-=3)>-1&&_.push(239,191,189);continue}p=o;continue}if(o<56320){(s-=3)>-1&&_.push(239,191,189),p=o;continue}o=(p-55296<<10|o-56320)+65536}else p&&(s-=3)>-1&&_.push(239,191,189);if(p=null,o<128){if((s-=1)<0)break;_.push(o)}else if(o<2048){if((s-=2)<0)break;_.push(o>>6|192,o&63|128)}else if(o<65536){if((s-=3)<0)break;_.push(o>>12|224,o>>6&63|128,o&63|128)}else if(o<1114112){if((s-=4)<0)break;_.push(o>>18|240,o>>12&63|128,o>>6&63|128,o&63|128)}else throw new Error("Invalid code point")}return _}function ho(l){const s=[];for(let o=0;o<l.length;++o)s.push(l.charCodeAt(o)&255);return s}function fo(l,s){let o,h,p;const _=[];for(let y=0;y<l.length&&!((s-=2)<0);++y)o=l.charCodeAt(y),h=o>>8,p=o%256,_.push(p),_.push(h);return _}function un(l){return S.toByteArray(uo(l))}function St(l,s,o,h){let p;for(p=0;p<h&&!(p+o>=s.length||p>=l.length);++p)s[p+o]=l[p];return p}function se(l,s){return l instanceof s||l!=null&&l.constructor!=null&&l.constructor.name!=null&&l.constructor.name===s.name}function tr(l){return l!==l}const po=function(){const l="0123456789abcdef",s=new Array(256);for(let o=0;o<16;++o){const h=o*16;for(let p=0;p<16;++p)s[h+p]=l[o]+l[p]}return s}();function me(l){return typeof BigInt>"u"?go:l}function go(){throw new Error("BigInt not supported")}})(e);const N=e.Buffer;t.Blob=e.Blob,t.BlobOptions=e.BlobOptions,t.Buffer=e.Buffer,t.File=e.File,t.FileOptions=e.FileOptions,t.INSPECT_MAX_BYTES=e.INSPECT_MAX_BYTES,t.SlowBuffer=e.SlowBuffer,t.TranscodeEncoding=e.TranscodeEncoding,t.atob=e.atob,t.btoa=e.btoa,t.constants=e.constants,t.default=N,t.isAscii=e.isAscii,t.isUtf8=e.isUtf8,t.kMaxLength=e.kMaxLength,t.kStringMaxLength=e.kStringMaxLength,t.resolveObjectURL=e.resolveObjectURL,t.transcode=e.transcode})(Ni);const Sn=Si(Ni),Zo=[{property:"name",enumerable:!1},{property:"message",enumerable:!1},{property:"stack",enumerable:!1},{property:"code",enumerable:!0}],mr=Symbol(".toJSON was called"),Qo=t=>{t[mr]=!0;const e=t.toJSON();return delete t[mr],e},Di=({from:t,seen:e,to_:r,forceEnumerable:n,maxDepth:i,depth:a})=>{const c=r||(Array.isArray(t)?[]:{});if(e.push(t),a>=i)return c;if(typeof t.toJSON=="function"&&t[mr]!==!0)return Qo(t);for(const[u,d]of Object.entries(t)){if(typeof Sn=="function"&&Sn.isBuffer(d)){c[u]="[object Buffer]";continue}if(d!==null&&typeof d=="object"&&typeof d.pipe=="function"){c[u]="[object Stream]";continue}if(typeof d!="function"){if(!d||typeof d!="object"){c[u]=d;continue}if(!e.includes(t[u])){a++,c[u]=Di({from:t[u],seen:[...e],forceEnumerable:n,maxDepth:i,depth:a});continue}c[u]="[Circular]"}}for(const{property:u,enumerable:d}of Zo)typeof t[u]=="string"&&Object.defineProperty(c,u,{value:t[u],enumerable:n?!0:d,configurable:!0,writable:!0});return c};function ea(t,e={}){const{maxDepth:r=Number.POSITIVE_INFINITY}=e;return typeof t=="object"&&t!==null?Di({from:t,seen:[],forceEnumerable:!0,maxDepth:r,depth:0}):typeof t=="function"?`[Function: ${t.name||"anonymous"}]`:t}let Mi=()=>({events:{},emit(t,...e){(this.events[t]||[]).forEach(r=>r(...e))},on(t,e){return(this.events[t]=this.events[t]||[]).push(e),()=>this.events[t]=(this.events[t]||[]).filter(r=>r!==e)}});var Jh=(t,e,r)=>{const n=Je.exports(),i=new Map,a=new Map,c=u=>{if(u.destination.context===t&&!u.destination.frameId&&!u.destination.tabId){r?.(u);const{transactionId:d,messageID:f,messageType:w}=u,g=()=>{const b=i.get(d);if(b){const{err:P,data:F}=u;if(P){const L=P,N=self[L.name],A=new(typeof N=="function"?N:Error)(L.message);for(const S in L)A[S]=L[S];b.reject(A)}else b.resolve(F);i.delete(d)}},E=async()=>{let b,P,F=!1;try{const L=a.get(f);if(typeof L=="function")b=await L({sender:u.origin,id:f,data:u.data,timestamp:u.timestamp});else throw F=!0,new Error(`[webext-bridge] No handler registered in '${t}' to accept messages with id '${f}'`)}catch(L){P=L}finally{if(P&&(u.err=ea(P)),c(Ri(Pi({},u),{messageType:"reply",data:b,origin:{context:t,tabId:null},destination:u.origin,hops:[]})),P&&!F)throw b}};switch(w){case"reply":return g();case"message":return E()}}return u.hops.push(`${t}::${n}`),e(u)};return{handleMessage:c,endTransaction:u=>{const d=i.get(u);d?.reject("Transaction was ended before it could complete"),i.delete(u)},sendMessage:(u,d,f="background")=>{const w=typeof f=="string"?Oi(f):f,g="Bridge#sendMessage ->";if(!w.context)throw new TypeError(`${g} Destination must be any one of known destinations`);return new Promise((E,b)=>{const P={messageID:u,data:d,destination:w,messageType:"message",transactionId:Je.exports(),origin:{context:t,tabId:null},hops:[],timestamp:Date.now()};i.set(P.transactionId,{resolve:E,reject:b});try{c(P)}catch(F){i.delete(P.transactionId),b(F)}})},onMessage:(u,d)=>(a.set(u,d),()=>a.delete(u))}},$e=class{constructor(t,e){this.endpointRuntime=t,this.streamInfo=e,this.emitter=Mi(),this.isClosed=!1,this.handleStreamClose=()=>{this.isClosed||(this.isClosed=!0,this.emitter.emit("closed",!0),this.emitter.events={})},$e.initDone||(t.onMessage("__crx_bridge_stream_transfer__",r=>{const{streamId:n,streamTransfer:i,action:a}=r.data,c=$e.openStreams.get(n);c&&!c.isClosed&&(a==="transfer"&&c.emitter.emit("message",i),a==="close"&&($e.openStreams.delete(n),c.handleStreamClose()))}),$e.initDone=!0),$e.openStreams.set(this.streamInfo.streamId,this)}get info(){return this.streamInfo}send(t){if(this.isClosed)throw new Error("Attempting to send a message over closed stream. Use stream.onClose(<callback>) to keep an eye on stream status");this.endpointRuntime.sendMessage("__crx_bridge_stream_transfer__",{streamId:this.streamInfo.streamId,streamTransfer:t,action:"transfer"},this.streamInfo.endpoint)}close(t){t&&this.send(t),this.handleStreamClose(),this.endpointRuntime.sendMessage("__crx_bridge_stream_transfer__",{streamId:this.streamInfo.streamId,streamTransfer:null,action:"close"},this.streamInfo.endpoint)}onMessage(t){return this.getDisposable("message",t)}onClose(t){return this.getDisposable("closed",t)}getDisposable(t,e){const r=this.emitter.on(t,e);return Object.assign(r,{dispose:r,close:r})}},Mt=$e;Mt.initDone=!1;Mt.openStreams=new Map;var Yh=t=>{const e=new Map,r=new Map,n=Mi();t.onMessage("__crx_bridge_stream_open__",c=>new Promise(u=>{const{sender:d,data:f}=c,{channel:w}=f;let g=!1,E=()=>{};const b=()=>{const P=r.get(w);typeof P=="function"?(P(new Mt(t,Ri(Pi({},f),{endpoint:d}))),g&&E(),u(!0)):g||(g=!0,E=n.on("did-change-stream-callbacks",b))};b()}));async function i(c,u){if(e.has(c))throw new Error("webext-bridge: A Stream is already open at this channel");const d=typeof u=="string"?Oi(u):u,f={streamId:Je.exports(),channel:c,endpoint:d},w=new Mt(t,f);return w.onClose(()=>e.delete(c)),await t.sendMessage("__crx_bridge_stream_open__",f,d),e.set(c,w),w}function a(c,u){if(r.has(c))throw new Error("webext-bridge: This channel has already been claimed. Stream allows only one-on-one communication");r.set(c,u),n.emit("did-change-stream-callbacks")}return{openStream:i,onOpenStreamChannel:a}};const Xh=(t,...e)=>{const r=e[1];return e[1]=async function(...n){try{return await r(...n)}catch(i){return{__error:{message:i.message,stack:Rr(i)}}}},t(...e)},Zh=async(t,...e)=>{const r=await t(...e);if(r?.__error){console.error(`sendMessage ${e[0]} error`,r.__error);const n=new Error(r.__error.message);throw n.stack+=`
`+r.__error.stack,n}return r},ht={};let kn=0;function ta(t,e){const r=`${kn}`;return kn+=1,ht[r]={handler:e,name:t},function(){delete ht[r]}}function ra(t,e){let r=!1;const n=ta(t,function(...i){r!==!0&&(r=!0,n(),e(...i))});return n}function xn(t,e){for(const r in ht)ht[r].name===t&&ht[r].handler.apply(null,e)}typeof figma<"u"?figma.ui.onmessage=function([t,...e]){xn(t,e)}:typeof window<"u"&&window.addEventListener("message",function(t){if(typeof t.data.pluginMessage>"u")return;const[e,...r]=t.data.pluginMessage;xn(e,r)});function na(t,e){return e.find(r=>r.id===t)||void 0}const Li=[{id:"desktop",name:"Desktop",width:1920,type:"preset"},{id:"laptop",name:"Laptop",width:1440,type:"preset"},{id:"tablet",name:"Tablet",width:1024,type:"preset"},{id:"phablet",name:"Phablet",width:768,type:"preset"},{id:"phone",name:"Phone",width:390,type:"preset"}];function Qh(t){const e=[...t,...Li],r=n=>e.find(i=>i.id===n)?.width??0;return(n,i)=>r(i)-r(n)}const Ui={id:"default",name:"Browser width",type:"preset",width:-1},Cn=[Ui,...Li];function ia(t,e){if(t&&"autoAction"in t)return;const r=t?.fullAutoActions??(Array.isArray(e)?e:void 0);return r?r.find(n=>n!=="clipboard")??null:t?.saveLocation?t.saveLocation==="gstorage"?"figma":"h2d":null}const ed=(t,e)=>{const r=e?.paginate&&t.allPages?!0:void 0;if(e?.disableVariations)return[{theme:"default",allPages:r}];const n=[],{multiImport:i}=t;for(const a of i.modes)for(const c of i.deviceIds){const u=na(c,t.devices);if(u){const d={theme:a,allPages:r};u.width>=0&&(d.width=u.width),n.push(d)}}return n};let Ce={devices:Cn,multiImport:{deviceIds:[Cn[0].id],modes:["default"]},autoAction:null,clipboardAutoLayout:!0},_r;const td=new Promise(t=>{_r=t});function rd(t){const[e,r]=Lo(Ce),n=Ei(),i=Fo(a=>{a.devices=a.devices.map(c=>c.id===Ui.id?{...c,name:n.current?`Browser (${n.current}px)`:"Browser"}:c),r(Ce=a)},[]);return Ii(()=>{chrome.storage.local.get(["settings","fullAutoActions"]).then(async a=>{const c={...Ce,...a.settings},u=ia(a.settings,a.fullAutoActions);u!==void 0&&(c.autoAction=u),delete c.fullAutoActions,_r(c),n.current=await t.current,i(c),u!==void 0&&await chrome.storage.local.set({settings:Ce})}).catch(()=>_r(Ce))},[i,t]),To([e,a=>{i({...Ce,...a}),chrome.storage.local.set({settings:Ce}).catch(c=>console.error(`Failed to save user settings: ${c}`))}])}async function nd(t){const e=await chrome.storage.local.get(["settings"]);t(e.settings)}var sa=Symbol.for("preact-signals");function Dr(){if(Ve>1)Ve--;else{for(var t,e=!1;dt!==void 0;){var r=dt;for(dt=void 0,wr++;r!==void 0;){var n=r.o;if(r.o=void 0,r.f&=-3,!(8&r.f)&&$i(r))try{r.c()}catch(i){e||(t=i,e=!0)}r=n}}if(wr=0,Ve--,e)throw t}}var $=void 0;function Bi(t){var e=$;$=void 0;try{return t()}finally{$=e}}var dt=void 0,Ve=0,wr=0,Lt=0;function Fi(t){if($!==void 0){var e=t.n;if(e===void 0||e.t!==$)return e={i:0,S:t,p:$.s,n:void 0,t:$,e:void 0,x:void 0,r:e},$.s!==void 0&&($.s.n=e),$.s=e,t.n=e,32&$.f&&t.S(e),e;if(e.i===-1)return e.i=0,e.n!==void 0&&(e.n.p=e.p,e.p!==void 0&&(e.p.n=e.n),e.p=$.s,e.n=void 0,$.s.n=e,$.s=e),e}}function Q(t,e){this.v=t,this.i=0,this.n=void 0,this.t=void 0,this.W=e?.watched,this.Z=e?.unwatched,this.name=e?.name}Q.prototype.brand=sa;Q.prototype.h=function(){return!0};Q.prototype.S=function(t){var e=this,r=this.t;r!==t&&t.e===void 0&&(t.x=r,this.t=t,r!==void 0?r.e=t:Bi(function(){var n;(n=e.W)==null||n.call(e)}))};Q.prototype.U=function(t){var e=this;if(this.t!==void 0){var r=t.e,n=t.x;r!==void 0&&(r.x=n,t.e=void 0),n!==void 0&&(n.e=r,t.x=void 0),t===this.t&&(this.t=n,n===void 0&&Bi(function(){var i;(i=e.Z)==null||i.call(e)}))}};Q.prototype.subscribe=function(t){var e=this;return Jt(function(){var r=e.value,n=$;$=void 0;try{t(r)}finally{$=n}},{name:"sub"})};Q.prototype.valueOf=function(){return this.value};Q.prototype.toString=function(){return this.value+""};Q.prototype.toJSON=function(){return this.value};Q.prototype.peek=function(){var t=$;$=void 0;try{return this.value}finally{$=t}};Object.defineProperty(Q.prototype,"value",{get:function(){var t=Fi(this);return t!==void 0&&(t.i=this.i),this.v},set:function(t){if(t!==this.v){if(wr>100)throw new Error("Cycle detected");this.v=t,this.i++,Lt++,Ve++;try{for(var e=this.t;e!==void 0;e=e.x)e.t.N()}finally{Dr()}}}});function tt(t,e){return new Q(t,e)}function $i(t){for(var e=t.s;e!==void 0;e=e.n)if(e.S.i!==e.i||!e.S.h()||e.S.i!==e.i)return!0;return!1}function Hi(t){for(var e=t.s;e!==void 0;e=e.n){var r=e.S.n;if(r!==void 0&&(e.r=r),e.S.n=e,e.i=-1,e.n===void 0){t.s=e;break}}}function Vi(t){for(var e=t.s,r=void 0;e!==void 0;){var n=e.p;e.i===-1?(e.S.U(e),n!==void 0&&(n.n=e.n),e.n!==void 0&&(e.n.p=n)):r=e,e.S.n=e.r,e.r!==void 0&&(e.r=void 0),e=n}t.s=r}function Me(t,e){Q.call(this,void 0),this.x=t,this.s=void 0,this.g=Lt-1,this.f=4,this.W=e?.watched,this.Z=e?.unwatched,this.name=e?.name}Me.prototype=new Q;Me.prototype.h=function(){if(this.f&=-3,1&this.f)return!1;if((36&this.f)==32||(this.f&=-5,this.g===Lt))return!0;if(this.g=Lt,this.f|=1,this.i>0&&!$i(this))return this.f&=-2,!0;var t=$;try{Hi(this),$=this;var e=this.x();(16&this.f||this.v!==e||this.i===0)&&(this.v=e,this.f&=-17,this.i++)}catch(r){this.v=r,this.f|=16,this.i++}return $=t,Vi(this),this.f&=-2,!0};Me.prototype.S=function(t){if(this.t===void 0){this.f|=36;for(var e=this.s;e!==void 0;e=e.n)e.S.S(e)}Q.prototype.S.call(this,t)};Me.prototype.U=function(t){if(this.t!==void 0&&(Q.prototype.U.call(this,t),this.t===void 0)){this.f&=-33;for(var e=this.s;e!==void 0;e=e.n)e.S.U(e)}};Me.prototype.N=function(){if(!(2&this.f)){this.f|=6;for(var t=this.t;t!==void 0;t=t.x)t.t.N()}};Object.defineProperty(Me.prototype,"value",{get:function(){if(1&this.f)throw new Error("Cycle detected");var t=Fi(this);if(this.h(),t!==void 0&&(t.i=this.i),16&this.f)throw this.v;return this.v}});function oa(t,e){return new Me(t,e)}function ji(t){var e=t.u;if(t.u=void 0,typeof e=="function"){Ve++;var r=$;$=void 0;try{e()}catch(n){throw t.f&=-2,t.f|=8,Mr(t),n}finally{$=r,Dr()}}}function Mr(t){for(var e=t.s;e!==void 0;e=e.n)e.S.U(e);t.x=void 0,t.s=void 0,ji(t)}function aa(t){if($!==this)throw new Error("Out-of-order effect");Vi(this),$=t,this.f&=-2,8&this.f&&Mr(this),Dr()}function rt(t,e){this.x=t,this.u=void 0,this.s=void 0,this.o=void 0,this.f=32,this.name=e?.name}rt.prototype.c=function(){var t=this.S();try{if(8&this.f||this.x===void 0)return;var e=this.x();typeof e=="function"&&(this.u=e)}finally{t()}};rt.prototype.S=function(){if(1&this.f)throw new Error("Cycle detected");this.f|=1,this.f&=-9,ji(this),Hi(this),Ve++;var t=$;return $=this,aa.bind(this,t)};rt.prototype.N=function(){2&this.f||(this.f|=2,this.o=dt,dt=this)};rt.prototype.d=function(){this.f|=8,1&this.f||Mr(this)};rt.prototype.dispose=function(){this.d()};function Jt(t,e){var r=new rt(t,e);try{r.c()}catch(i){throw r.d(),i}var n=r.d.bind(r);return n[Symbol.dispose]=n,n}var nr;function nt(t,e){j[t]=e.bind(null,j[t]||function(){})}function Ut(t){nr&&nr(),nr=t&&t.S()}function Wi(t){var e=this,r=t.data,n=la(r);n.value=r;var i=Kt(function(){for(var a=e.__v;a=a.__;)if(a.__c){a.__c.__$f|=4;break}return e.__$u.c=function(){e.base.data=i.peek()},oa(function(){var c=n.value.value;return c===0?0:c===!0?"":c||""})},[]);return i.value}Wi.displayName="_st";Object.defineProperties(Q.prototype,{constructor:{configurable:!0,value:void 0},type:{configurable:!0,value:Wi},props:{configurable:!0,get:function(){return{data:this}}},__b:{configurable:!0,value:1}});nt("__b",function(t,e){if(typeof e.type=="string"){var r,n=e.props;for(var i in n)if(i!=="children"){var a=n[i];a instanceof Q&&(r||(e.__np=r={}),r[i]=a,n[i]=a.peek())}}t(e)});nt("__r",function(t,e){Ut();var r,n=e.__c;n&&(n.__$f&=-2,(r=n.__$u)===void 0&&(n.__$u=r=function(i){var a;return Jt(function(){a=this}),a.c=function(){n.__$f|=1,n.setState({})},a}())),Ut(r),t(e)});nt("__e",function(t,e,r,n){Ut(),t(e,r,n)});nt("diffed",function(t,e){Ut();var r;if(typeof e.type=="string"&&(r=e.__e)){var n=e.__np,i=e.props;if(n){var a=r.U;if(a)for(var c in a){var u=a[c];u!==void 0&&!(c in n)&&(u.d(),a[c]=void 0)}else r.U=a={};for(var d in n){var f=a[d],w=n[d];f===void 0?(f=ca(r,d,w,i),a[d]=f):f.o(w,i)}}}t(e)});function ca(t,e,r,n){var i=e in t&&t.ownerSVGElement===void 0,a=tt(r);return{o:function(c,u){a.value=c,n=u},d:Jt(function(){var c=a.value.value;n[e]!==c&&(n[e]=c,i?t[e]=c:c?t.setAttribute(e,c):t.removeAttribute(e))})}}nt("unmount",function(t,e){if(typeof e.type=="string"){var r=e.__e;if(r){var n=r.U;if(n){r.U=void 0;for(var i in n){var a=n[i];a&&a.d()}}}}else{var c=e.__c;if(c){var u=c.__$u;u&&(c.__$u=void 0,u.d())}}t(e)});nt("__h",function(t,e,r,n){n<3&&(e.__$f|=2),t(e,r,n)});ut.prototype.shouldComponentUpdate=function(t,e){var r=this.__$u;if(!(r&&r.s!==void 0||4&this.__$f)||3&this.__$f)return!0;for(var n in e)return!0;for(var i in t)if(i!=="__source"&&t[i]!==this.props[i])return!0;for(var a in this.props)if(!(a in t))return!0;return!1};function la(t){return Kt(function(){return tt(t)},[])}function id(t){var e=Ei(t);e.current=t,Ii(function(){return Jt(function(){return e.current()})},[])}const ua=typeof window>"u"?function(t,...e){figma.ui.postMessage([t,...e])}:function(t,...e){window.parent.postMessage({pluginMessage:[t,...e]},"*")};function zi(t,...e){({}).NO_DEBUG_LOGS||console.log("EMIT",t,e),ua(t,...e)}let ha=0;function da(t,...e){const r=ha++;return new Promise((n,i)=>{ra(`${String(t)}-response-${r}`,a=>{if("error"in a){const{message:c,stack:u,name:d}=a.error,f=new Error(c);d&&(f.name=d),f.cause=new fa(c,u),i(f)}else n(a.result)}),zi(String(t),[r,...e])})}class fa extends Error{constructor(e,r){super(e),this.stack=r}}function Gi(t,...e){return da(t,...e)}const ft=globalThis.chrome?.storage?.local,qi=typeof window<"u"&&window.self===window.top;async function pa(t){return ft?(await ft.get(t))[t]:qi?window.localStorage.getItem(t):Gi("client-storage-request",t)}async function Ki(t,e){ft?e===void 0?await ft.remove(t):await ft.set({[t]:e}):qi?e===void 0?window.localStorage.removeItem(t):window.localStorage.setItem(t,e):await Gi("client-storage-set",t,e)}function Ji(t,e){try{zi("notify-request",t,e)}catch{console.log(t,e)}}const ga=()=>Math.floor(Date.now()/1e3);var Yi={};(function(t){Object.defineProperties(t,{__esModule:{value:!0},[Symbol.toStringTag]:{value:"Module"}});function e(I){return I&&I.__esModule&&Object.prototype.hasOwnProperty.call(I,"default")?I.default:I}var r={exports:{}},n=r.exports={},i,a;function c(){throw new Error("setTimeout has not been defined")}function u(){throw new Error("clearTimeout has not been defined")}(function(){try{typeof setTimeout=="function"?i=setTimeout:i=c}catch{i=c}try{typeof clearTimeout=="function"?a=clearTimeout:a=u}catch{a=u}})();function d(I){if(i===setTimeout)return setTimeout(I,0);if((i===c||!i)&&setTimeout)return i=setTimeout,setTimeout(I,0);try{return i(I,0)}catch{try{return i.call(null,I,0)}catch{return i.call(this,I,0)}}}function f(I){if(a===clearTimeout)return clearTimeout(I);if((a===u||!a)&&clearTimeout)return a=clearTimeout,clearTimeout(I);try{return a(I)}catch{try{return a.call(null,I)}catch{return a.call(this,I)}}}var w=[],g=!1,E,b=-1;function P(){!g||!E||(g=!1,E.length?w=E.concat(w):b=-1,w.length&&F())}function F(){if(!g){var I=d(P);g=!0;for(var D=w.length;D;){for(E=w,w=[];++b<D;)E&&E[b].run();b=-1,D=w.length}E=null,g=!1,f(I)}}n.nextTick=function(I){var D=new Array(arguments.length-1);if(arguments.length>1)for(var R=1;R<arguments.length;R++)D[R-1]=arguments[R];w.push(new L(I,D)),w.length===1&&!g&&d(F)};function L(I,D){this.fun=I,this.array=D}L.prototype.run=function(){this.fun.apply(null,this.array)},n.title="browser",n.browser=!0,n.env={},n.argv=[],n.version="",n.versions={};function N(){}n.on=N,n.addListener=N,n.once=N,n.off=N,n.removeListener=N,n.removeAllListeners=N,n.emit=N,n.prependListener=N,n.prependOnceListener=N,n.listeners=function(I){return[]},n.binding=function(I){throw new Error("process.binding is not supported")},n.cwd=function(){return"/"},n.chdir=function(I){throw new Error("process.chdir is not supported")},n.umask=function(){return 0};var A=r.exports;const S=e(A);t.default=S,t.process=S})(Yi);const Pn=Si(Yi);/**
 * @license
 * Copyright 2025 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const ma=()=>{};/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Xi=function(t){const e=[];let r=0;for(let n=0;n<t.length;n++){let i=t.charCodeAt(n);i<128?e[r++]=i:i<2048?(e[r++]=i>>6|192,e[r++]=i&63|128):(i&64512)===55296&&n+1<t.length&&(t.charCodeAt(n+1)&64512)===56320?(i=65536+((i&1023)<<10)+(t.charCodeAt(++n)&1023),e[r++]=i>>18|240,e[r++]=i>>12&63|128,e[r++]=i>>6&63|128,e[r++]=i&63|128):(e[r++]=i>>12|224,e[r++]=i>>6&63|128,e[r++]=i&63|128)}return e},_a=function(t){const e=[];let r=0,n=0;for(;r<t.length;){const i=t[r++];if(i<128)e[n++]=String.fromCharCode(i);else if(i>191&&i<224){const a=t[r++];e[n++]=String.fromCharCode((i&31)<<6|a&63)}else if(i>239&&i<365){const a=t[r++],c=t[r++],u=t[r++],d=((i&7)<<18|(a&63)<<12|(c&63)<<6|u&63)-65536;e[n++]=String.fromCharCode(55296+(d>>10)),e[n++]=String.fromCharCode(56320+(d&1023))}else{const a=t[r++],c=t[r++];e[n++]=String.fromCharCode((i&15)<<12|(a&63)<<6|c&63)}}return e.join("")},Zi={byteToCharMap_:null,charToByteMap_:null,byteToCharMapWebSafe_:null,charToByteMapWebSafe_:null,ENCODED_VALS_BASE:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789",get ENCODED_VALS(){return this.ENCODED_VALS_BASE+"+/="},get ENCODED_VALS_WEBSAFE(){return this.ENCODED_VALS_BASE+"-_."},HAS_NATIVE_SUPPORT:typeof atob=="function",encodeByteArray(t,e){if(!Array.isArray(t))throw Error("encodeByteArray takes an array as a parameter");this.init_();const r=e?this.byteToCharMapWebSafe_:this.byteToCharMap_,n=[];for(let i=0;i<t.length;i+=3){const a=t[i],c=i+1<t.length,u=c?t[i+1]:0,d=i+2<t.length,f=d?t[i+2]:0,w=a>>2,g=(a&3)<<4|u>>4;let E=(u&15)<<2|f>>6,b=f&63;d||(b=64,c||(E=64)),n.push(r[w],r[g],r[E],r[b])}return n.join("")},encodeString(t,e){return this.HAS_NATIVE_SUPPORT&&!e?btoa(t):this.encodeByteArray(Xi(t),e)},decodeString(t,e){return this.HAS_NATIVE_SUPPORT&&!e?atob(t):_a(this.decodeStringToByteArray(t,e))},decodeStringToByteArray(t,e){this.init_();const r=e?this.charToByteMapWebSafe_:this.charToByteMap_,n=[];for(let i=0;i<t.length;){const a=r[t.charAt(i++)],u=i<t.length?r[t.charAt(i)]:0;++i;const f=i<t.length?r[t.charAt(i)]:64;++i;const g=i<t.length?r[t.charAt(i)]:64;if(++i,a==null||u==null||f==null||g==null)throw new wa;const E=a<<2|u>>4;if(n.push(E),f!==64){const b=u<<4&240|f>>2;if(n.push(b),g!==64){const P=f<<6&192|g;n.push(P)}}}return n},init_(){if(!this.byteToCharMap_){this.byteToCharMap_={},this.charToByteMap_={},this.byteToCharMapWebSafe_={},this.charToByteMapWebSafe_={};for(let t=0;t<this.ENCODED_VALS.length;t++)this.byteToCharMap_[t]=this.ENCODED_VALS.charAt(t),this.charToByteMap_[this.byteToCharMap_[t]]=t,this.byteToCharMapWebSafe_[t]=this.ENCODED_VALS_WEBSAFE.charAt(t),this.charToByteMapWebSafe_[this.byteToCharMapWebSafe_[t]]=t,t>=this.ENCODED_VALS_BASE.length&&(this.charToByteMap_[this.ENCODED_VALS_WEBSAFE.charAt(t)]=t,this.charToByteMapWebSafe_[this.ENCODED_VALS.charAt(t)]=t)}}};class wa extends Error{constructor(){super(...arguments),this.name="DecodeBase64StringError"}}const ya=function(t){const e=Xi(t);return Zi.encodeByteArray(e,!0)},Qi=function(t){return ya(t).replace(/\./g,"")},es=function(t){try{return Zi.decodeString(t,!0)}catch(e){console.error("base64Decode failed: ",e)}return null};/**
 * @license
 * Copyright 2022 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function va(){if(typeof self<"u")return self;if(typeof window<"u")return window;if(typeof global<"u")return global;throw new Error("Unable to locate global object.")}/**
 * @license
 * Copyright 2022 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const ba=()=>va().__FIREBASE_DEFAULTS__,Aa=()=>{if(typeof Pn>"u"||typeof Pn.env>"u")return;const t={}.__FIREBASE_DEFAULTS__;if(t)return JSON.parse(t)},Ia=()=>{if(typeof document>"u")return;let t;try{t=document.cookie.match(/__FIREBASE_DEFAULTS__=([^;]+)/)}catch{return}const e=t&&es(t[1]);return e&&JSON.parse(e)},Lr=()=>{try{return ma()||ba()||Aa()||Ia()}catch(t){console.info(`Unable to get __FIREBASE_DEFAULTS__ due to: ${t}`);return}},Ea=t=>Lr()?.emulatorHosts?.[t],ts=()=>Lr()?.config,rs=t=>Lr()?.[`_${t}`];/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Ta{constructor(){this.reject=()=>{},this.resolve=()=>{},this.promise=new Promise((e,r)=>{this.resolve=e,this.reject=r})}wrapCallback(e){return(r,n)=>{r?this.reject(r):this.resolve(n),typeof e=="function"&&(this.promise.catch(()=>{}),e.length===1?e(r):e(r,n))}}}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function ee(){return typeof navigator<"u"&&typeof navigator.userAgent=="string"?navigator.userAgent:""}function Sa(){return typeof window<"u"&&!!(window.cordova||window.phonegap||window.PhoneGap)&&/ios|iphone|ipod|ipad|android|blackberry|iemobile/i.test(ee())}function ka(){return typeof navigator<"u"&&navigator.userAgent==="Cloudflare-Workers"}function xa(){const t=typeof chrome=="object"?chrome.runtime:typeof browser=="object"?browser.runtime:void 0;return typeof t=="object"&&t.id!==void 0}function Ca(){return typeof navigator=="object"&&navigator.product==="ReactNative"}function Pa(){const t=ee();return t.indexOf("MSIE ")>=0||t.indexOf("Trident/")>=0}function Ra(){try{return typeof indexedDB=="object"}catch{return!1}}function Oa(){return new Promise((t,e)=>{try{let r=!0;const n="validate-browser-context-for-indexeddb-analytics-module",i=self.indexedDB.open(n);i.onsuccess=()=>{i.result.close(),r||self.indexedDB.deleteDatabase(n),t(!0)},i.onupgradeneeded=()=>{r=!1},i.onerror=()=>{e(i.error?.message||"")}}catch(r){e(r)}})}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Na="FirebaseError";class Se extends Error{constructor(e,r,n){super(r),this.code=e,this.customData=n,this.name=Na,Object.setPrototypeOf(this,Se.prototype),Error.captureStackTrace&&Error.captureStackTrace(this,vt.prototype.create)}}class vt{constructor(e,r,n){this.service=e,this.serviceName=r,this.errors=n}create(e,...r){const n=r[0]||{},i=`${this.service}/${e}`,a=this.errors[e],c=a?Da(a,n):"Error",u=`${this.serviceName}: ${c} (${i}).`;return new Se(i,u,n)}}function Da(t,e){return t.replace(Ma,(r,n)=>{const i=e[n];return i!=null?String(i):`<${n}?>`})}const Ma=/\{\$([^}]+)}/g;function La(t){for(const e in t)if(Object.prototype.hasOwnProperty.call(t,e))return!1;return!0}function Ye(t,e){if(t===e)return!0;const r=Object.keys(t),n=Object.keys(e);for(const i of r){if(!n.includes(i))return!1;const a=t[i],c=e[i];if(Rn(a)&&Rn(c)){if(!Ye(a,c))return!1}else if(a!==c)return!1}for(const i of n)if(!r.includes(i))return!1;return!0}function Rn(t){return t!==null&&typeof t=="object"}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function bt(t){const e=[];for(const[r,n]of Object.entries(t))Array.isArray(n)?n.forEach(i=>{e.push(encodeURIComponent(r)+"="+encodeURIComponent(i))}):e.push(encodeURIComponent(r)+"="+encodeURIComponent(n));return e.length?"&"+e.join("&"):""}function Ua(t,e){const r=new Ba(t,e);return r.subscribe.bind(r)}class Ba{constructor(e,r){this.observers=[],this.unsubscribes=[],this.observerCount=0,this.task=Promise.resolve(),this.finalized=!1,this.onNoObservers=r,this.task.then(()=>{e(this)}).catch(n=>{this.error(n)})}next(e){this.forEachObserver(r=>{r.next(e)})}error(e){this.forEachObserver(r=>{r.error(e)}),this.close(e)}complete(){this.forEachObserver(e=>{e.complete()}),this.close()}subscribe(e,r,n){let i;if(e===void 0&&r===void 0&&n===void 0)throw new Error("Missing Observer.");Fa(e,["next","error","complete"])?i=e:i={next:e,error:r,complete:n},i.next===void 0&&(i.next=ir),i.error===void 0&&(i.error=ir),i.complete===void 0&&(i.complete=ir);const a=this.unsubscribeOne.bind(this,this.observers.length);return this.finalized&&this.task.then(()=>{try{this.finalError?i.error(this.finalError):i.complete()}catch{}}),this.observers.push(i),a}unsubscribeOne(e){this.observers===void 0||this.observers[e]===void 0||(delete this.observers[e],this.observerCount-=1,this.observerCount===0&&this.onNoObservers!==void 0&&this.onNoObservers(this))}forEachObserver(e){if(!this.finalized)for(let r=0;r<this.observers.length;r++)this.sendOne(r,e)}sendOne(e,r){this.task.then(()=>{if(this.observers!==void 0&&this.observers[e]!==void 0)try{r(this.observers[e])}catch(n){typeof console<"u"&&console.error&&console.error(n)}})}close(e){this.finalized||(this.finalized=!0,e!==void 0&&(this.finalError=e),this.task.then(()=>{this.observers=void 0,this.onNoObservers=void 0}))}}function Fa(t,e){if(typeof t!="object"||t===null)return!1;for(const r of e)if(r in t&&typeof t[r]=="function")return!0;return!1}function ir(){}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Le(t){return t&&t._delegate?t._delegate:t}/**
 * @license
 * Copyright 2025 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Ur(t){try{return(t.startsWith("http://")||t.startsWith("https://")?new URL(t).hostname:t).endsWith(".cloudworkstations.dev")}catch{return!1}}async function $a(t){return(await fetch(t,{credentials:"include"})).ok}class Xe{constructor(e,r,n){this.name=e,this.instanceFactory=r,this.type=n,this.multipleInstances=!1,this.serviceProps={},this.instantiationMode="LAZY",this.onInstanceCreated=null}setInstantiationMode(e){return this.instantiationMode=e,this}setMultipleInstances(e){return this.multipleInstances=e,this}setServiceProps(e){return this.serviceProps=e,this}setInstanceCreatedCallback(e){return this.onInstanceCreated=e,this}}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Pe="[DEFAULT]";/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Ha{constructor(e,r){this.name=e,this.container=r,this.component=null,this.instances=new Map,this.instancesDeferred=new Map,this.instancesOptions=new Map,this.onInitCallbacks=new Map}get(e){const r=this.normalizeInstanceIdentifier(e);if(!this.instancesDeferred.has(r)){const n=new Ta;if(this.instancesDeferred.set(r,n),this.isInitialized(r)||this.shouldAutoInitialize())try{const i=this.getOrInitializeService({instanceIdentifier:r});i&&n.resolve(i)}catch{}}return this.instancesDeferred.get(r).promise}getImmediate(e){const r=this.normalizeInstanceIdentifier(e?.identifier),n=e?.optional??!1;if(this.isInitialized(r)||this.shouldAutoInitialize())try{return this.getOrInitializeService({instanceIdentifier:r})}catch(i){if(n)return null;throw i}else{if(n)return null;throw Error(`Service ${this.name} is not available`)}}getComponent(){return this.component}setComponent(e){if(e.name!==this.name)throw Error(`Mismatching Component ${e.name} for Provider ${this.name}.`);if(this.component)throw Error(`Component for ${this.name} has already been provided`);if(this.component=e,!!this.shouldAutoInitialize()){if(ja(e))try{this.getOrInitializeService({instanceIdentifier:Pe})}catch{}for(const[r,n]of this.instancesDeferred.entries()){const i=this.normalizeInstanceIdentifier(r);try{const a=this.getOrInitializeService({instanceIdentifier:i});n.resolve(a)}catch{}}}}clearInstance(e=Pe){this.instancesDeferred.delete(e),this.instancesOptions.delete(e),this.instances.delete(e)}async delete(){const e=Array.from(this.instances.values());await Promise.all([...e.filter(r=>"INTERNAL"in r).map(r=>r.INTERNAL.delete()),...e.filter(r=>"_delete"in r).map(r=>r._delete())])}isComponentSet(){return this.component!=null}isInitialized(e=Pe){return this.instances.has(e)}getOptions(e=Pe){return this.instancesOptions.get(e)||{}}initialize(e={}){const{options:r={}}=e,n=this.normalizeInstanceIdentifier(e.instanceIdentifier);if(this.isInitialized(n))throw Error(`${this.name}(${n}) has already been initialized`);if(!this.isComponentSet())throw Error(`Component ${this.name} has not been registered yet`);const i=this.getOrInitializeService({instanceIdentifier:n,options:r});for(const[a,c]of this.instancesDeferred.entries()){const u=this.normalizeInstanceIdentifier(a);n===u&&c.resolve(i)}return i}onInit(e,r){const n=this.normalizeInstanceIdentifier(r),i=this.onInitCallbacks.get(n)??new Set;i.add(e),this.onInitCallbacks.set(n,i);const a=this.instances.get(n);return a&&e(a,n),()=>{i.delete(e)}}invokeOnInitCallbacks(e,r){const n=this.onInitCallbacks.get(r);if(!!n)for(const i of n)try{i(e,r)}catch{}}getOrInitializeService({instanceIdentifier:e,options:r={}}){let n=this.instances.get(e);if(!n&&this.component&&(n=this.component.instanceFactory(this.container,{instanceIdentifier:Va(e),options:r}),this.instances.set(e,n),this.instancesOptions.set(e,r),this.invokeOnInitCallbacks(n,e),this.component.onInstanceCreated))try{this.component.onInstanceCreated(this.container,e,n)}catch{}return n||null}normalizeInstanceIdentifier(e=Pe){return this.component?this.component.multipleInstances?e:Pe:e}shouldAutoInitialize(){return!!this.component&&this.component.instantiationMode!=="EXPLICIT"}}function Va(t){return t===Pe?void 0:t}function ja(t){return t.instantiationMode==="EAGER"}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Wa{constructor(e){this.name=e,this.providers=new Map}addComponent(e){const r=this.getProvider(e.name);if(r.isComponentSet())throw new Error(`Component ${e.name} has already been registered with ${this.name}`);r.setComponent(e)}addOrOverwriteComponent(e){this.getProvider(e.name).isComponentSet()&&this.providers.delete(e.name),this.addComponent(e)}getProvider(e){if(this.providers.has(e))return this.providers.get(e);const r=new Ha(e,this);return this.providers.set(e,r),r}getProviders(){return Array.from(this.providers.values())}}/**
 * @license
 * Copyright 2017 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */var V;(function(t){t[t.DEBUG=0]="DEBUG",t[t.VERBOSE=1]="VERBOSE",t[t.INFO=2]="INFO",t[t.WARN=3]="WARN",t[t.ERROR=4]="ERROR",t[t.SILENT=5]="SILENT"})(V||(V={}));const za={debug:V.DEBUG,verbose:V.VERBOSE,info:V.INFO,warn:V.WARN,error:V.ERROR,silent:V.SILENT},Ga=V.INFO,qa={[V.DEBUG]:"log",[V.VERBOSE]:"log",[V.INFO]:"info",[V.WARN]:"warn",[V.ERROR]:"error"},Ka=(t,e,...r)=>{if(e<t.logLevel)return;const n=new Date().toISOString(),i=qa[e];if(i)console[i](`[${n}]  ${t.name}:`,...r);else throw new Error(`Attempted to log a message with an invalid logType (value: ${e})`)};class ns{constructor(e){this.name=e,this._logLevel=Ga,this._logHandler=Ka,this._userLogHandler=null}get logLevel(){return this._logLevel}set logLevel(e){if(!(e in V))throw new TypeError(`Invalid value "${e}" assigned to \`logLevel\``);this._logLevel=e}setLogLevel(e){this._logLevel=typeof e=="string"?za[e]:e}get logHandler(){return this._logHandler}set logHandler(e){if(typeof e!="function")throw new TypeError("Value assigned to `logHandler` must be a function");this._logHandler=e}get userLogHandler(){return this._userLogHandler}set userLogHandler(e){this._userLogHandler=e}debug(...e){this._userLogHandler&&this._userLogHandler(this,V.DEBUG,...e),this._logHandler(this,V.DEBUG,...e)}log(...e){this._userLogHandler&&this._userLogHandler(this,V.VERBOSE,...e),this._logHandler(this,V.VERBOSE,...e)}info(...e){this._userLogHandler&&this._userLogHandler(this,V.INFO,...e),this._logHandler(this,V.INFO,...e)}warn(...e){this._userLogHandler&&this._userLogHandler(this,V.WARN,...e),this._logHandler(this,V.WARN,...e)}error(...e){this._userLogHandler&&this._userLogHandler(this,V.ERROR,...e),this._logHandler(this,V.ERROR,...e)}}const Ja=(t,e)=>e.some(r=>t instanceof r);let On,Nn;function Ya(){return On||(On=[IDBDatabase,IDBObjectStore,IDBIndex,IDBCursor,IDBTransaction])}function Xa(){return Nn||(Nn=[IDBCursor.prototype.advance,IDBCursor.prototype.continue,IDBCursor.prototype.continuePrimaryKey])}const is=new WeakMap,yr=new WeakMap,ss=new WeakMap,sr=new WeakMap,Br=new WeakMap;function Za(t){const e=new Promise((r,n)=>{const i=()=>{t.removeEventListener("success",a),t.removeEventListener("error",c)},a=()=>{r(Ae(t.result)),i()},c=()=>{n(t.error),i()};t.addEventListener("success",a),t.addEventListener("error",c)});return e.then(r=>{r instanceof IDBCursor&&is.set(r,t)}).catch(()=>{}),Br.set(e,t),e}function Qa(t){if(yr.has(t))return;const e=new Promise((r,n)=>{const i=()=>{t.removeEventListener("complete",a),t.removeEventListener("error",c),t.removeEventListener("abort",c)},a=()=>{r(),i()},c=()=>{n(t.error||new DOMException("AbortError","AbortError")),i()};t.addEventListener("complete",a),t.addEventListener("error",c),t.addEventListener("abort",c)});yr.set(t,e)}let vr={get(t,e,r){if(t instanceof IDBTransaction){if(e==="done")return yr.get(t);if(e==="objectStoreNames")return t.objectStoreNames||ss.get(t);if(e==="store")return r.objectStoreNames[1]?void 0:r.objectStore(r.objectStoreNames[0])}return Ae(t[e])},set(t,e,r){return t[e]=r,!0},has(t,e){return t instanceof IDBTransaction&&(e==="done"||e==="store")?!0:e in t}};function ec(t){vr=t(vr)}function tc(t){return t===IDBDatabase.prototype.transaction&&!("objectStoreNames"in IDBTransaction.prototype)?function(e,...r){const n=t.call(or(this),e,...r);return ss.set(n,e.sort?e.sort():[e]),Ae(n)}:Xa().includes(t)?function(...e){return t.apply(or(this),e),Ae(is.get(this))}:function(...e){return Ae(t.apply(or(this),e))}}function rc(t){return typeof t=="function"?tc(t):(t instanceof IDBTransaction&&Qa(t),Ja(t,Ya())?new Proxy(t,vr):t)}function Ae(t){if(t instanceof IDBRequest)return Za(t);if(sr.has(t))return sr.get(t);const e=rc(t);return e!==t&&(sr.set(t,e),Br.set(e,t)),e}const or=t=>Br.get(t);function nc(t,e,{blocked:r,upgrade:n,blocking:i,terminated:a}={}){const c=indexedDB.open(t,e),u=Ae(c);return n&&c.addEventListener("upgradeneeded",d=>{n(Ae(c.result),d.oldVersion,d.newVersion,Ae(c.transaction),d)}),r&&c.addEventListener("blocked",d=>r(d.oldVersion,d.newVersion,d)),u.then(d=>{a&&d.addEventListener("close",()=>a()),i&&d.addEventListener("versionchange",f=>i(f.oldVersion,f.newVersion,f))}).catch(()=>{}),u}const ic=["get","getKey","getAll","getAllKeys","count"],sc=["put","add","delete","clear"],ar=new Map;function Dn(t,e){if(!(t instanceof IDBDatabase&&!(e in t)&&typeof e=="string"))return;if(ar.get(e))return ar.get(e);const r=e.replace(/FromIndex$/,""),n=e!==r,i=sc.includes(r);if(!(r in(n?IDBIndex:IDBObjectStore).prototype)||!(i||ic.includes(r)))return;const a=async function(c,...u){const d=this.transaction(c,i?"readwrite":"readonly");let f=d.store;return n&&(f=f.index(u.shift())),(await Promise.all([f[r](...u),i&&d.done]))[0]};return ar.set(e,a),a}ec(t=>({...t,get:(e,r,n)=>Dn(e,r)||t.get(e,r,n),has:(e,r)=>!!Dn(e,r)||t.has(e,r)}));/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class oc{constructor(e){this.container=e}getPlatformInfoString(){return this.container.getProviders().map(r=>{if(ac(r)){const n=r.getImmediate();return`${n.library}/${n.version}`}else return null}).filter(r=>r).join(" ")}}function ac(t){return t.getComponent()?.type==="VERSION"}const br="@firebase/app",Mn="0.14.10";/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const fe=new ns("@firebase/app"),cc="@firebase/app-compat",lc="@firebase/analytics-compat",uc="@firebase/analytics",hc="@firebase/app-check-compat",dc="@firebase/app-check",fc="@firebase/auth",pc="@firebase/auth-compat",gc="@firebase/database",mc="@firebase/data-connect",_c="@firebase/database-compat",wc="@firebase/functions",yc="@firebase/functions-compat",vc="@firebase/installations",bc="@firebase/installations-compat",Ac="@firebase/messaging",Ic="@firebase/messaging-compat",Ec="@firebase/performance",Tc="@firebase/performance-compat",Sc="@firebase/remote-config",kc="@firebase/remote-config-compat",xc="@firebase/storage",Cc="@firebase/storage-compat",Pc="@firebase/firestore",Rc="@firebase/ai",Oc="@firebase/firestore-compat",Nc="firebase",Dc="12.11.0";/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Ar="[DEFAULT]",Mc={[br]:"fire-core",[cc]:"fire-core-compat",[uc]:"fire-analytics",[lc]:"fire-analytics-compat",[dc]:"fire-app-check",[hc]:"fire-app-check-compat",[fc]:"fire-auth",[pc]:"fire-auth-compat",[gc]:"fire-rtdb",[mc]:"fire-data-connect",[_c]:"fire-rtdb-compat",[wc]:"fire-fn",[yc]:"fire-fn-compat",[vc]:"fire-iid",[bc]:"fire-iid-compat",[Ac]:"fire-fcm",[Ic]:"fire-fcm-compat",[Ec]:"fire-perf",[Tc]:"fire-perf-compat",[Sc]:"fire-rc",[kc]:"fire-rc-compat",[xc]:"fire-gcs",[Cc]:"fire-gcs-compat",[Pc]:"fire-fst",[Oc]:"fire-fst-compat",[Rc]:"fire-vertex","fire-js":"fire-js",[Nc]:"fire-js-all"};/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Bt=new Map,Lc=new Map,Ir=new Map;function Ln(t,e){try{t.container.addComponent(e)}catch(r){fe.debug(`Component ${e.name} failed to register with FirebaseApp ${t.name}`,r)}}function mt(t){const e=t.name;if(Ir.has(e))return fe.debug(`There were multiple attempts to register component ${e}.`),!1;Ir.set(e,t);for(const r of Bt.values())Ln(r,t);for(const r of Lc.values())Ln(r,t);return!0}function os(t,e){const r=t.container.getProvider("heartbeat").getImmediate({optional:!0});return r&&r.triggerHeartbeat(),t.container.getProvider(e)}function le(t){return t==null?!1:t.settings!==void 0}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Uc={["no-app"]:"No Firebase App '{$appName}' has been created - call initializeApp() first",["bad-app-name"]:"Illegal App name: '{$appName}'",["duplicate-app"]:"Firebase App named '{$appName}' already exists with different options or config",["app-deleted"]:"Firebase App named '{$appName}' already deleted",["server-app-deleted"]:"Firebase Server App has been deleted",["no-options"]:"Need to provide options, when not being deployed to hosting via source.",["invalid-app-argument"]:"firebase.{$appName}() takes either no argument or a Firebase App instance.",["invalid-log-argument"]:"First argument to `onLog` must be null or a function.",["idb-open"]:"Error thrown when opening IndexedDB. Original error: {$originalErrorMessage}.",["idb-get"]:"Error thrown when reading from IndexedDB. Original error: {$originalErrorMessage}.",["idb-set"]:"Error thrown when writing to IndexedDB. Original error: {$originalErrorMessage}.",["idb-delete"]:"Error thrown when deleting from IndexedDB. Original error: {$originalErrorMessage}.",["finalization-registry-not-supported"]:"FirebaseServerApp deleteOnDeref field defined but the JS runtime does not support FinalizationRegistry.",["invalid-server-app-environment"]:"FirebaseServerApp is not for use in browser environments."},Ie=new vt("app","Firebase",Uc);/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Bc{constructor(e,r,n){this._isDeleted=!1,this._options={...e},this._config={...r},this._name=r.name,this._automaticDataCollectionEnabled=r.automaticDataCollectionEnabled,this._container=n,this.container.addComponent(new Xe("app",()=>this,"PUBLIC"))}get automaticDataCollectionEnabled(){return this.checkDestroyed(),this._automaticDataCollectionEnabled}set automaticDataCollectionEnabled(e){this.checkDestroyed(),this._automaticDataCollectionEnabled=e}get name(){return this.checkDestroyed(),this._name}get options(){return this.checkDestroyed(),this._options}get config(){return this.checkDestroyed(),this._config}get container(){return this._container}get isDeleted(){return this._isDeleted}set isDeleted(e){this._isDeleted=e}checkDestroyed(){if(this.isDeleted)throw Ie.create("app-deleted",{appName:this._name})}}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const At=Dc;function Fc(t,e={}){let r=t;typeof e!="object"&&(e={name:e});const n={name:Ar,automaticDataCollectionEnabled:!0,...e},i=n.name;if(typeof i!="string"||!i)throw Ie.create("bad-app-name",{appName:String(i)});if(r||(r=ts()),!r)throw Ie.create("no-options");const a=Bt.get(i);if(a){if(Ye(r,a.options)&&Ye(n,a.config))return a;throw Ie.create("duplicate-app",{appName:i})}const c=new Wa(i);for(const d of Ir.values())c.addComponent(d);const u=new Bc(r,n,c);return Bt.set(i,u),u}function $c(t=Ar){const e=Bt.get(t);if(!e&&t===Ar&&ts())return Fc();if(!e)throw Ie.create("no-app",{appName:t});return e}function je(t,e,r){let n=Mc[t]??t;r&&(n+=`-${r}`);const i=n.match(/\s|\//),a=e.match(/\s|\//);if(i||a){const c=[`Unable to register library "${n}" with version "${e}":`];i&&c.push(`library name "${n}" contains illegal characters (whitespace or "/")`),i&&a&&c.push("and"),a&&c.push(`version name "${e}" contains illegal characters (whitespace or "/")`),fe.warn(c.join(" "));return}mt(new Xe(`${n}-version`,()=>({library:n,version:e}),"VERSION"))}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Hc="firebase-heartbeat-database",Vc=1,_t="firebase-heartbeat-store";let cr=null;function as(){return cr||(cr=nc(Hc,Vc,{upgrade:(t,e)=>{switch(e){case 0:try{t.createObjectStore(_t)}catch(r){console.warn(r)}}}}).catch(t=>{throw Ie.create("idb-open",{originalErrorMessage:t.message})})),cr}async function jc(t){try{const r=(await as()).transaction(_t),n=await r.objectStore(_t).get(cs(t));return await r.done,n}catch(e){if(e instanceof Se)fe.warn(e.message);else{const r=Ie.create("idb-get",{originalErrorMessage:e?.message});fe.warn(r.message)}}}async function Un(t,e){try{const n=(await as()).transaction(_t,"readwrite");await n.objectStore(_t).put(e,cs(t)),await n.done}catch(r){if(r instanceof Se)fe.warn(r.message);else{const n=Ie.create("idb-set",{originalErrorMessage:r?.message});fe.warn(n.message)}}}function cs(t){return`${t.name}!${t.options.appId}`}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Wc=1024,zc=30;class Gc{constructor(e){this.container=e,this._heartbeatsCache=null;const r=this.container.getProvider("app").getImmediate();this._storage=new Kc(r),this._heartbeatsCachePromise=this._storage.read().then(n=>(this._heartbeatsCache=n,n))}async triggerHeartbeat(){try{const r=this.container.getProvider("platform-logger").getImmediate().getPlatformInfoString(),n=Bn();if(this._heartbeatsCache?.heartbeats==null&&(this._heartbeatsCache=await this._heartbeatsCachePromise,this._heartbeatsCache?.heartbeats==null)||this._heartbeatsCache.lastSentHeartbeatDate===n||this._heartbeatsCache.heartbeats.some(i=>i.date===n))return;if(this._heartbeatsCache.heartbeats.push({date:n,agent:r}),this._heartbeatsCache.heartbeats.length>zc){const i=Jc(this._heartbeatsCache.heartbeats);this._heartbeatsCache.heartbeats.splice(i,1)}return this._storage.overwrite(this._heartbeatsCache)}catch(e){fe.warn(e)}}async getHeartbeatsHeader(){try{if(this._heartbeatsCache===null&&await this._heartbeatsCachePromise,this._heartbeatsCache?.heartbeats==null||this._heartbeatsCache.heartbeats.length===0)return"";const e=Bn(),{heartbeatsToSend:r,unsentEntries:n}=qc(this._heartbeatsCache.heartbeats),i=Qi(JSON.stringify({version:2,heartbeats:r}));return this._heartbeatsCache.lastSentHeartbeatDate=e,n.length>0?(this._heartbeatsCache.heartbeats=n,await this._storage.overwrite(this._heartbeatsCache)):(this._heartbeatsCache.heartbeats=[],this._storage.overwrite(this._heartbeatsCache)),i}catch(e){return fe.warn(e),""}}}function Bn(){return new Date().toISOString().substring(0,10)}function qc(t,e=Wc){const r=[];let n=t.slice();for(const i of t){const a=r.find(c=>c.agent===i.agent);if(a){if(a.dates.push(i.date),Fn(r)>e){a.dates.pop();break}}else if(r.push({agent:i.agent,dates:[i.date]}),Fn(r)>e){r.pop();break}n=n.slice(1)}return{heartbeatsToSend:r,unsentEntries:n}}class Kc{constructor(e){this.app=e,this._canUseIndexedDBPromise=this.runIndexedDBEnvironmentCheck()}async runIndexedDBEnvironmentCheck(){return Ra()?Oa().then(()=>!0).catch(()=>!1):!1}async read(){if(await this._canUseIndexedDBPromise){const r=await jc(this.app);return r?.heartbeats?r:{heartbeats:[]}}else return{heartbeats:[]}}async overwrite(e){if(await this._canUseIndexedDBPromise){const n=await this.read();return Un(this.app,{lastSentHeartbeatDate:e.lastSentHeartbeatDate??n.lastSentHeartbeatDate,heartbeats:e.heartbeats})}else return}async add(e){if(await this._canUseIndexedDBPromise){const n=await this.read();return Un(this.app,{lastSentHeartbeatDate:e.lastSentHeartbeatDate??n.lastSentHeartbeatDate,heartbeats:[...n.heartbeats,...e.heartbeats]})}else return}}function Fn(t){return Qi(JSON.stringify({version:2,heartbeats:t})).length}function Jc(t){if(t.length===0)return-1;let e=0,r=t[0].date;for(let n=1;n<t.length;n++)t[n].date<r&&(r=t[n].date,e=n);return e}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Yc(t){mt(new Xe("platform-logger",e=>new oc(e),"PRIVATE")),mt(new Xe("heartbeat",e=>new Gc(e),"PRIVATE")),je(br,Mn,t),je(br,Mn,"esm2020"),je("fire-js","")}Yc("");function ls(){return{["dependent-sdk-initialized-before-auth"]:"Another Firebase SDK was initialized and is trying to use Auth before Auth is initialized. Please be sure to call `initializeAuth` or `getAuth` before starting any other Firebase SDK."}}const Xc=ls,us=new vt("auth","Firebase",ls());/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Ft=new ns("@firebase/auth");function Zc(t,...e){Ft.logLevel<=V.WARN&&Ft.warn(`Auth (${At}): ${t}`,...e)}function Pt(t,...e){Ft.logLevel<=V.ERROR&&Ft.error(`Auth (${At}): ${t}`,...e)}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function pe(t,...e){throw Fr(t,...e)}function oe(t,...e){return Fr(t,...e)}function hs(t,e,r){const n={...Xc(),[e]:r};return new vt("auth","Firebase",n).create(e,{appName:t.name})}function Ne(t){return hs(t,"operation-not-supported-in-this-environment","Operations that alter the current user are not supported in conjunction with FirebaseServerApp")}function Fr(t,...e){if(typeof t!="string"){const r=e[0],n=[...e.slice(1)];return n[0]&&(n[0].appName=t.name),t._errorFactory.create(r,...n)}return us.create(t,...e)}function M(t,e,...r){if(!t)throw Fr(e,...r)}function he(t){const e="INTERNAL ASSERTION FAILED: "+t;throw Pt(e),new Error(e)}function ge(t,e){t||he(e)}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Er(){return typeof self<"u"&&self.location?.href||""}function Qc(){return $n()==="http:"||$n()==="https:"}function $n(){return typeof self<"u"&&self.location?.protocol||null}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function el(){return typeof navigator<"u"&&navigator&&"onLine"in navigator&&typeof navigator.onLine=="boolean"&&(Qc()||xa()||"connection"in navigator)?navigator.onLine:!0}function tl(){if(typeof navigator>"u")return null;const t=navigator;return t.languages&&t.languages[0]||t.language||null}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class It{constructor(e,r){this.shortDelay=e,this.longDelay=r,ge(r>e,"Short delay should be less than long delay!"),this.isMobile=Sa()||Ca()}get(){return el()?this.isMobile?this.longDelay:this.shortDelay:Math.min(5e3,this.shortDelay)}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function $r(t,e){ge(t.emulator,"Emulator should always be set here");const{url:r}=t.emulator;return e?`${r}${e.startsWith("/")?e.slice(1):e}`:r}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class ds{static initialize(e,r,n){this.fetchImpl=e,r&&(this.headersImpl=r),n&&(this.responseImpl=n)}static fetch(){if(this.fetchImpl)return this.fetchImpl;if(typeof self<"u"&&"fetch"in self)return self.fetch;if(typeof globalThis<"u"&&globalThis.fetch)return globalThis.fetch;if(typeof fetch<"u")return fetch;he("Could not find fetch implementation, make sure you call FetchProvider.initialize() with an appropriate polyfill")}static headers(){if(this.headersImpl)return this.headersImpl;if(typeof self<"u"&&"Headers"in self)return self.Headers;if(typeof globalThis<"u"&&globalThis.Headers)return globalThis.Headers;if(typeof Headers<"u")return Headers;he("Could not find Headers implementation, make sure you call FetchProvider.initialize() with an appropriate polyfill")}static response(){if(this.responseImpl)return this.responseImpl;if(typeof self<"u"&&"Response"in self)return self.Response;if(typeof globalThis<"u"&&globalThis.Response)return globalThis.Response;if(typeof Response<"u")return Response;he("Could not find Response implementation, make sure you call FetchProvider.initialize() with an appropriate polyfill")}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const rl={CREDENTIAL_MISMATCH:"custom-token-mismatch",MISSING_CUSTOM_TOKEN:"internal-error",INVALID_IDENTIFIER:"invalid-email",MISSING_CONTINUE_URI:"internal-error",INVALID_PASSWORD:"wrong-password",MISSING_PASSWORD:"missing-password",INVALID_LOGIN_CREDENTIALS:"invalid-credential",EMAIL_EXISTS:"email-already-in-use",PASSWORD_LOGIN_DISABLED:"operation-not-allowed",INVALID_IDP_RESPONSE:"invalid-credential",INVALID_PENDING_TOKEN:"invalid-credential",FEDERATED_USER_ID_ALREADY_LINKED:"credential-already-in-use",MISSING_REQ_TYPE:"internal-error",EMAIL_NOT_FOUND:"user-not-found",RESET_PASSWORD_EXCEED_LIMIT:"too-many-requests",EXPIRED_OOB_CODE:"expired-action-code",INVALID_OOB_CODE:"invalid-action-code",MISSING_OOB_CODE:"internal-error",CREDENTIAL_TOO_OLD_LOGIN_AGAIN:"requires-recent-login",INVALID_ID_TOKEN:"invalid-user-token",TOKEN_EXPIRED:"user-token-expired",USER_NOT_FOUND:"user-token-expired",TOO_MANY_ATTEMPTS_TRY_LATER:"too-many-requests",PASSWORD_DOES_NOT_MEET_REQUIREMENTS:"password-does-not-meet-requirements",INVALID_CODE:"invalid-verification-code",INVALID_SESSION_INFO:"invalid-verification-id",INVALID_TEMPORARY_PROOF:"invalid-credential",MISSING_SESSION_INFO:"missing-verification-id",SESSION_EXPIRED:"code-expired",MISSING_ANDROID_PACKAGE_NAME:"missing-android-pkg-name",UNAUTHORIZED_DOMAIN:"unauthorized-continue-uri",INVALID_OAUTH_CLIENT_ID:"invalid-oauth-client-id",ADMIN_ONLY_OPERATION:"admin-restricted-operation",INVALID_MFA_PENDING_CREDENTIAL:"invalid-multi-factor-session",MFA_ENROLLMENT_NOT_FOUND:"multi-factor-info-not-found",MISSING_MFA_ENROLLMENT_ID:"missing-multi-factor-info",MISSING_MFA_PENDING_CREDENTIAL:"missing-multi-factor-session",SECOND_FACTOR_EXISTS:"second-factor-already-in-use",SECOND_FACTOR_LIMIT_EXCEEDED:"maximum-second-factor-count-exceeded",BLOCKING_FUNCTION_ERROR_RESPONSE:"internal-error",RECAPTCHA_NOT_ENABLED:"recaptcha-not-enabled",MISSING_RECAPTCHA_TOKEN:"missing-recaptcha-token",INVALID_RECAPTCHA_TOKEN:"invalid-recaptcha-token",INVALID_RECAPTCHA_ACTION:"invalid-recaptcha-action",MISSING_CLIENT_TYPE:"missing-client-type",MISSING_RECAPTCHA_VERSION:"missing-recaptcha-version",INVALID_RECAPTCHA_VERSION:"invalid-recaptcha-version",INVALID_REQ_TYPE:"invalid-req-type"};/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const nl=["/v1/accounts:signInWithCustomToken","/v1/accounts:signInWithEmailLink","/v1/accounts:signInWithIdp","/v1/accounts:signInWithPassword","/v1/accounts:signInWithPhoneNumber","/v1/token"],il=new It(3e4,6e4);function Hr(t,e){return t.tenantId&&!e.tenantId?{...e,tenantId:t.tenantId}:e}async function it(t,e,r,n,i={}){return fs(t,i,async()=>{let a={},c={};n&&(e==="GET"?c=n:a={body:JSON.stringify(n)});const u=bt({key:t.config.apiKey,...c}).slice(1),d=await t._getAdditionalHeaders();d["Content-Type"]="application/json",t.languageCode&&(d["X-Firebase-Locale"]=t.languageCode);const f={method:e,headers:d,...a};return ka()||(f.referrerPolicy="no-referrer"),t.emulatorConfig&&Ur(t.emulatorConfig.host)&&(f.credentials="include"),ds.fetch()(await ps(t,t.config.apiHost,r,u),f)})}async function fs(t,e,r){t._canInitEmulator=!1;const n={...rl,...e};try{const i=new ol(t),a=await Promise.race([r(),i.promise]);i.clearNetworkTimeout();const c=await a.json();if("needConfirmation"in c)throw xt(t,"account-exists-with-different-credential",c);if(a.ok&&!("errorMessage"in c))return c;{const u=a.ok?c.errorMessage:c.error.message,[d,f]=u.split(" : ");if(d==="FEDERATED_USER_ID_ALREADY_LINKED")throw xt(t,"credential-already-in-use",c);if(d==="EMAIL_EXISTS")throw xt(t,"email-already-in-use",c);if(d==="USER_DISABLED")throw xt(t,"user-disabled",c);const w=n[d]||d.toLowerCase().replace(/[_\s]+/g,"-");if(f)throw hs(t,w,f);pe(t,w)}}catch(i){if(i instanceof Se)throw i;pe(t,"network-request-failed",{message:String(i)})}}async function sl(t,e,r,n,i={}){const a=await it(t,e,r,n,i);return"mfaPendingCredential"in a&&pe(t,"multi-factor-auth-required",{_serverResponse:a}),a}async function ps(t,e,r,n){const i=`${e}${r}?${n}`,a=t,c=a.config.emulator?$r(t.config,i):`${t.config.apiScheme}://${i}`;return nl.includes(r)&&(await a._persistenceManagerAvailable,a._getPersistenceType()==="COOKIE")?a._getPersistence()._getFinalTarget(c).toString():c}class ol{clearNetworkTimeout(){clearTimeout(this.timer)}constructor(e){this.auth=e,this.timer=null,this.promise=new Promise((r,n)=>{this.timer=setTimeout(()=>n(oe(this.auth,"network-request-failed")),il.get())})}}function xt(t,e,r){const n={appName:t.name};r.email&&(n.email=r.email),r.phoneNumber&&(n.phoneNumber=r.phoneNumber);const i=oe(t,e,n);return i.customData._tokenResponse=r,i}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function al(t,e){return it(t,"POST","/v1/accounts:delete",e)}async function $t(t,e){return it(t,"POST","/v1/accounts:lookup",e)}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function pt(t){if(!!t)try{const e=new Date(Number(t));if(!isNaN(e.getTime()))return e.toUTCString()}catch{}}async function cl(t,e=!1){const r=Le(t),n=await r.getIdToken(e),i=Vr(n);M(i&&i.exp&&i.auth_time&&i.iat,r.auth,"internal-error");const a=typeof i.firebase=="object"?i.firebase:void 0,c=a?.sign_in_provider;return{claims:i,token:n,authTime:pt(lr(i.auth_time)),issuedAtTime:pt(lr(i.iat)),expirationTime:pt(lr(i.exp)),signInProvider:c||null,signInSecondFactor:a?.sign_in_second_factor||null}}function lr(t){return Number(t)*1e3}function Vr(t){const[e,r,n]=t.split(".");if(e===void 0||r===void 0||n===void 0)return Pt("JWT malformed, contained fewer than 3 sections"),null;try{const i=es(r);return i?JSON.parse(i):(Pt("Failed to decode base64 JWT payload"),null)}catch(i){return Pt("Caught error parsing JWT payload as JSON",i?.toString()),null}}function Hn(t){const e=Vr(t);return M(e,"internal-error"),M(typeof e.exp<"u","internal-error"),M(typeof e.iat<"u","internal-error"),Number(e.exp)-Number(e.iat)}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function wt(t,e,r=!1){if(r)return e;try{return await e}catch(n){throw n instanceof Se&&ll(n)&&t.auth.currentUser===t&&await t.auth.signOut(),n}}function ll({code:t}){return t==="auth/user-disabled"||t==="auth/user-token-expired"}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class ul{constructor(e){this.user=e,this.isRunning=!1,this.timerId=null,this.errorBackoff=3e4}_start(){this.isRunning||(this.isRunning=!0,this.schedule())}_stop(){!this.isRunning||(this.isRunning=!1,this.timerId!==null&&clearTimeout(this.timerId))}getInterval(e){if(e){const r=this.errorBackoff;return this.errorBackoff=Math.min(this.errorBackoff*2,96e4),r}else{this.errorBackoff=3e4;const n=(this.user.stsTokenManager.expirationTime??0)-Date.now()-3e5;return Math.max(0,n)}}schedule(e=!1){if(!this.isRunning)return;const r=this.getInterval(e);this.timerId=setTimeout(async()=>{await this.iteration()},r)}async iteration(){try{await this.user.getIdToken(!0)}catch(e){e?.code==="auth/network-request-failed"&&this.schedule(!0);return}this.schedule()}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Tr{constructor(e,r){this.createdAt=e,this.lastLoginAt=r,this._initializeTime()}_initializeTime(){this.lastSignInTime=pt(this.lastLoginAt),this.creationTime=pt(this.createdAt)}_copy(e){this.createdAt=e.createdAt,this.lastLoginAt=e.lastLoginAt,this._initializeTime()}toJSON(){return{createdAt:this.createdAt,lastLoginAt:this.lastLoginAt}}}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function Ht(t){const e=t.auth,r=await t.getIdToken(),n=await wt(t,$t(e,{idToken:r}));M(n?.users.length,e,"internal-error");const i=n.users[0];t._notifyReloadListener(i);const a=i.providerUserInfo?.length?gs(i.providerUserInfo):[],c=dl(t.providerData,a),u=t.isAnonymous,d=!(t.email&&i.passwordHash)&&!c?.length,f=u?d:!1,w={uid:i.localId,displayName:i.displayName||null,photoURL:i.photoUrl||null,email:i.email||null,emailVerified:i.emailVerified||!1,phoneNumber:i.phoneNumber||null,tenantId:i.tenantId||null,providerData:c,metadata:new Tr(i.createdAt,i.lastLoginAt),isAnonymous:f};Object.assign(t,w)}async function hl(t){const e=Le(t);await Ht(e),await e.auth._persistUserIfCurrent(e),e.auth._notifyListenersIfCurrent(e)}function dl(t,e){return[...t.filter(n=>!e.some(i=>i.providerId===n.providerId)),...e]}function gs(t){return t.map(({providerId:e,...r})=>({providerId:e,uid:r.rawId||"",displayName:r.displayName||null,email:r.email||null,phoneNumber:r.phoneNumber||null,photoURL:r.photoUrl||null}))}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function fl(t,e){const r=await fs(t,{},async()=>{const n=bt({grant_type:"refresh_token",refresh_token:e}).slice(1),{tokenApiHost:i,apiKey:a}=t.config,c=await ps(t,i,"/v1/token",`key=${a}`),u=await t._getAdditionalHeaders();u["Content-Type"]="application/x-www-form-urlencoded";const d={method:"POST",headers:u,body:n};return t.emulatorConfig&&Ur(t.emulatorConfig.host)&&(d.credentials="include"),ds.fetch()(c,d)});return{accessToken:r.access_token,expiresIn:r.expires_in,refreshToken:r.refresh_token}}async function pl(t,e){return it(t,"POST","/v2/accounts:revokeToken",Hr(t,e))}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class We{constructor(){this.refreshToken=null,this.accessToken=null,this.expirationTime=null}get isExpired(){return!this.expirationTime||Date.now()>this.expirationTime-3e4}updateFromServerResponse(e){M(e.idToken,"internal-error"),M(typeof e.idToken<"u","internal-error"),M(typeof e.refreshToken<"u","internal-error");const r="expiresIn"in e&&typeof e.expiresIn<"u"?Number(e.expiresIn):Hn(e.idToken);this.updateTokensAndExpiration(e.idToken,e.refreshToken,r)}updateFromIdToken(e){M(e.length!==0,"internal-error");const r=Hn(e);this.updateTokensAndExpiration(e,null,r)}async getToken(e,r=!1){return!r&&this.accessToken&&!this.isExpired?this.accessToken:(M(this.refreshToken,e,"user-token-expired"),this.refreshToken?(await this.refresh(e,this.refreshToken),this.accessToken):null)}clearRefreshToken(){this.refreshToken=null}async refresh(e,r){const{accessToken:n,refreshToken:i,expiresIn:a}=await fl(e,r);this.updateTokensAndExpiration(n,i,Number(a))}updateTokensAndExpiration(e,r,n){this.refreshToken=r||null,this.accessToken=e||null,this.expirationTime=Date.now()+n*1e3}static fromJSON(e,r){const{refreshToken:n,accessToken:i,expirationTime:a}=r,c=new We;return n&&(M(typeof n=="string","internal-error",{appName:e}),c.refreshToken=n),i&&(M(typeof i=="string","internal-error",{appName:e}),c.accessToken=i),a&&(M(typeof a=="number","internal-error",{appName:e}),c.expirationTime=a),c}toJSON(){return{refreshToken:this.refreshToken,accessToken:this.accessToken,expirationTime:this.expirationTime}}_assign(e){this.accessToken=e.accessToken,this.refreshToken=e.refreshToken,this.expirationTime=e.expirationTime}_clone(){return Object.assign(new We,this.toJSON())}_performRefresh(){return he("not implemented")}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function _e(t,e){M(typeof t=="string"||typeof t>"u","internal-error",{appName:e})}class ne{constructor({uid:e,auth:r,stsTokenManager:n,...i}){this.providerId="firebase",this.proactiveRefresh=new ul(this),this.reloadUserInfo=null,this.reloadListener=null,this.uid=e,this.auth=r,this.stsTokenManager=n,this.accessToken=n.accessToken,this.displayName=i.displayName||null,this.email=i.email||null,this.emailVerified=i.emailVerified||!1,this.phoneNumber=i.phoneNumber||null,this.photoURL=i.photoURL||null,this.isAnonymous=i.isAnonymous||!1,this.tenantId=i.tenantId||null,this.providerData=i.providerData?[...i.providerData]:[],this.metadata=new Tr(i.createdAt||void 0,i.lastLoginAt||void 0)}async getIdToken(e){const r=await wt(this,this.stsTokenManager.getToken(this.auth,e));return M(r,this.auth,"internal-error"),this.accessToken!==r&&(this.accessToken=r,await this.auth._persistUserIfCurrent(this),this.auth._notifyListenersIfCurrent(this)),r}getIdTokenResult(e){return cl(this,e)}reload(){return hl(this)}_assign(e){this!==e&&(M(this.uid===e.uid,this.auth,"internal-error"),this.displayName=e.displayName,this.photoURL=e.photoURL,this.email=e.email,this.emailVerified=e.emailVerified,this.phoneNumber=e.phoneNumber,this.isAnonymous=e.isAnonymous,this.tenantId=e.tenantId,this.providerData=e.providerData.map(r=>({...r})),this.metadata._copy(e.metadata),this.stsTokenManager._assign(e.stsTokenManager))}_clone(e){const r=new ne({...this,auth:e,stsTokenManager:this.stsTokenManager._clone()});return r.metadata._copy(this.metadata),r}_onReload(e){M(!this.reloadListener,this.auth,"internal-error"),this.reloadListener=e,this.reloadUserInfo&&(this._notifyReloadListener(this.reloadUserInfo),this.reloadUserInfo=null)}_notifyReloadListener(e){this.reloadListener?this.reloadListener(e):this.reloadUserInfo=e}_startProactiveRefresh(){this.proactiveRefresh._start()}_stopProactiveRefresh(){this.proactiveRefresh._stop()}async _updateTokensIfNecessary(e,r=!1){let n=!1;e.idToken&&e.idToken!==this.stsTokenManager.accessToken&&(this.stsTokenManager.updateFromServerResponse(e),n=!0),r&&await Ht(this),await this.auth._persistUserIfCurrent(this),n&&this.auth._notifyListenersIfCurrent(this)}async delete(){if(le(this.auth.app))return Promise.reject(Ne(this.auth));const e=await this.getIdToken();return await wt(this,al(this.auth,{idToken:e})),this.stsTokenManager.clearRefreshToken(),this.auth.signOut()}toJSON(){return{uid:this.uid,email:this.email||void 0,emailVerified:this.emailVerified,displayName:this.displayName||void 0,isAnonymous:this.isAnonymous,photoURL:this.photoURL||void 0,phoneNumber:this.phoneNumber||void 0,tenantId:this.tenantId||void 0,providerData:this.providerData.map(e=>({...e})),stsTokenManager:this.stsTokenManager.toJSON(),_redirectEventId:this._redirectEventId,...this.metadata.toJSON(),apiKey:this.auth.config.apiKey,appName:this.auth.name}}get refreshToken(){return this.stsTokenManager.refreshToken||""}static _fromJSON(e,r){const n=r.displayName??void 0,i=r.email??void 0,a=r.phoneNumber??void 0,c=r.photoURL??void 0,u=r.tenantId??void 0,d=r._redirectEventId??void 0,f=r.createdAt??void 0,w=r.lastLoginAt??void 0,{uid:g,emailVerified:E,isAnonymous:b,providerData:P,stsTokenManager:F}=r;M(g&&F,e,"internal-error");const L=We.fromJSON(this.name,F);M(typeof g=="string",e,"internal-error"),_e(n,e.name),_e(i,e.name),M(typeof E=="boolean",e,"internal-error"),M(typeof b=="boolean",e,"internal-error"),_e(a,e.name),_e(c,e.name),_e(u,e.name),_e(d,e.name),_e(f,e.name),_e(w,e.name);const N=new ne({uid:g,auth:e,email:i,emailVerified:E,displayName:n,isAnonymous:b,photoURL:c,phoneNumber:a,tenantId:u,stsTokenManager:L,createdAt:f,lastLoginAt:w});return P&&Array.isArray(P)&&(N.providerData=P.map(A=>({...A}))),d&&(N._redirectEventId=d),N}static async _fromIdTokenResponse(e,r,n=!1){const i=new We;i.updateFromServerResponse(r);const a=new ne({uid:r.localId,auth:e,stsTokenManager:i,isAnonymous:n});return await Ht(a),a}static async _fromGetAccountInfoResponse(e,r,n){const i=r.users[0];M(i.localId!==void 0,"internal-error");const a=i.providerUserInfo!==void 0?gs(i.providerUserInfo):[],c=!(i.email&&i.passwordHash)&&!a?.length,u=new We;u.updateFromIdToken(n);const d=new ne({uid:i.localId,auth:e,stsTokenManager:u,isAnonymous:c}),f={uid:i.localId,displayName:i.displayName||null,photoURL:i.photoUrl||null,email:i.email||null,emailVerified:i.emailVerified||!1,phoneNumber:i.phoneNumber||null,tenantId:i.tenantId||null,providerData:a,metadata:new Tr(i.createdAt,i.lastLoginAt),isAnonymous:!(i.email&&i.passwordHash)&&!a?.length};return Object.assign(d,f),d}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Vn=new Map;function de(t){ge(t instanceof Function,"Expected a class definition");let e=Vn.get(t);return e?(ge(e instanceof t,"Instance stored in cache mismatched with class"),e):(e=new t,Vn.set(t,e),e)}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class ms{constructor(){this.type="NONE",this.storage={}}async _isAvailable(){return!0}async _set(e,r){this.storage[e]=r}async _get(e){const r=this.storage[e];return r===void 0?null:r}async _remove(e){delete this.storage[e]}_addListener(e,r){}_removeListener(e,r){}}ms.type="NONE";const jn=ms;/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Rt(t,e,r){return`firebase:${t}:${e}:${r}`}class ze{constructor(e,r,n){this.persistence=e,this.auth=r,this.userKey=n;const{config:i,name:a}=this.auth;this.fullUserKey=Rt(this.userKey,i.apiKey,a),this.fullPersistenceKey=Rt("persistence",i.apiKey,a),this.boundEventHandler=r._onStorageEvent.bind(r),this.persistence._addListener(this.fullUserKey,this.boundEventHandler)}setCurrentUser(e){return this.persistence._set(this.fullUserKey,e.toJSON())}async getCurrentUser(){const e=await this.persistence._get(this.fullUserKey);if(!e)return null;if(typeof e=="string"){const r=await $t(this.auth,{idToken:e}).catch(()=>{});return r?ne._fromGetAccountInfoResponse(this.auth,r,e):null}return ne._fromJSON(this.auth,e)}removeCurrentUser(){return this.persistence._remove(this.fullUserKey)}savePersistenceForRedirect(){return this.persistence._set(this.fullPersistenceKey,this.persistence.type)}async setPersistence(e){if(this.persistence===e)return;const r=await this.getCurrentUser();if(await this.removeCurrentUser(),this.persistence=e,r)return this.setCurrentUser(r)}delete(){this.persistence._removeListener(this.fullUserKey,this.boundEventHandler)}static async create(e,r,n="authUser"){if(!r.length)return new ze(de(jn),e,n);const i=(await Promise.all(r.map(async f=>{if(await f._isAvailable())return f}))).filter(f=>f);let a=i[0]||de(jn);const c=Rt(n,e.config.apiKey,e.name);let u=null;for(const f of r)try{const w=await f._get(c);if(w){let g;if(typeof w=="string"){const E=await $t(e,{idToken:w}).catch(()=>{});if(!E)break;g=await ne._fromGetAccountInfoResponse(e,E,w)}else g=ne._fromJSON(e,w);f!==a&&(u=g),a=f;break}}catch{}const d=i.filter(f=>f._shouldAllowMigration);return!a._shouldAllowMigration||!d.length?new ze(a,e,n):(a=d[0],u&&await a._set(c,u.toJSON()),await Promise.all(r.map(async f=>{if(f!==a)try{await f._remove(c)}catch{}})),new ze(a,e,n))}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Wn(t){const e=t.toLowerCase();if(e.includes("opera/")||e.includes("opr/")||e.includes("opios/"))return"Opera";if(vs(e))return"IEMobile";if(e.includes("msie")||e.includes("trident/"))return"IE";if(e.includes("edge/"))return"Edge";if(_s(e))return"Firefox";if(e.includes("silk/"))return"Silk";if(As(e))return"Blackberry";if(Is(e))return"Webos";if(ws(e))return"Safari";if((e.includes("chrome/")||ys(e))&&!e.includes("edge/"))return"Chrome";if(bs(e))return"Android";{const r=/([a-zA-Z\d\.]+)\/[a-zA-Z\d\.]*$/,n=t.match(r);if(n?.length===2)return n[1]}return"Other"}function _s(t=ee()){return/firefox\//i.test(t)}function ws(t=ee()){const e=t.toLowerCase();return e.includes("safari/")&&!e.includes("chrome/")&&!e.includes("crios/")&&!e.includes("android")}function ys(t=ee()){return/crios\//i.test(t)}function vs(t=ee()){return/iemobile/i.test(t)}function bs(t=ee()){return/android/i.test(t)}function As(t=ee()){return/blackberry/i.test(t)}function Is(t=ee()){return/webos/i.test(t)}function jr(t=ee()){return/iphone|ipad|ipod/i.test(t)||/macintosh/i.test(t)&&/mobile/i.test(t)}function gl(t=ee()){return jr(t)&&!!window.navigator?.standalone}function ml(){return Pa()&&document.documentMode===10}function Es(t=ee()){return jr(t)||bs(t)||Is(t)||As(t)||/windows phone/i.test(t)||vs(t)}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Ts(t,e=[]){let r;switch(t){case"Browser":r=Wn(ee());break;case"Worker":r=`${Wn(ee())}-${t}`;break;default:r=t}const n=e.length?e.join(","):"FirebaseCore-web";return`${r}/JsCore/${At}/${n}`}/**
 * @license
 * Copyright 2022 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class _l{constructor(e){this.auth=e,this.queue=[]}pushCallback(e,r){const n=a=>new Promise((c,u)=>{try{const d=e(a);c(d)}catch(d){u(d)}});n.onAbort=r,this.queue.push(n);const i=this.queue.length-1;return()=>{this.queue[i]=()=>Promise.resolve()}}async runMiddleware(e){if(this.auth.currentUser===e)return;const r=[];try{for(const n of this.queue)await n(e),n.onAbort&&r.push(n.onAbort)}catch(n){r.reverse();for(const i of r)try{i()}catch{}throw this.auth._errorFactory.create("login-blocked",{originalMessage:n?.message})}}}/**
 * @license
 * Copyright 2023 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function wl(t,e={}){return it(t,"GET","/v2/passwordPolicy",Hr(t,e))}/**
 * @license
 * Copyright 2023 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const yl=6;class vl{constructor(e){const r=e.customStrengthOptions;this.customStrengthOptions={},this.customStrengthOptions.minPasswordLength=r.minPasswordLength??yl,r.maxPasswordLength&&(this.customStrengthOptions.maxPasswordLength=r.maxPasswordLength),r.containsLowercaseCharacter!==void 0&&(this.customStrengthOptions.containsLowercaseLetter=r.containsLowercaseCharacter),r.containsUppercaseCharacter!==void 0&&(this.customStrengthOptions.containsUppercaseLetter=r.containsUppercaseCharacter),r.containsNumericCharacter!==void 0&&(this.customStrengthOptions.containsNumericCharacter=r.containsNumericCharacter),r.containsNonAlphanumericCharacter!==void 0&&(this.customStrengthOptions.containsNonAlphanumericCharacter=r.containsNonAlphanumericCharacter),this.enforcementState=e.enforcementState,this.enforcementState==="ENFORCEMENT_STATE_UNSPECIFIED"&&(this.enforcementState="OFF"),this.allowedNonAlphanumericCharacters=e.allowedNonAlphanumericCharacters?.join("")??"",this.forceUpgradeOnSignin=e.forceUpgradeOnSignin??!1,this.schemaVersion=e.schemaVersion}validatePassword(e){const r={isValid:!0,passwordPolicy:this};return this.validatePasswordLengthOptions(e,r),this.validatePasswordCharacterOptions(e,r),r.isValid&&(r.isValid=r.meetsMinPasswordLength??!0),r.isValid&&(r.isValid=r.meetsMaxPasswordLength??!0),r.isValid&&(r.isValid=r.containsLowercaseLetter??!0),r.isValid&&(r.isValid=r.containsUppercaseLetter??!0),r.isValid&&(r.isValid=r.containsNumericCharacter??!0),r.isValid&&(r.isValid=r.containsNonAlphanumericCharacter??!0),r}validatePasswordLengthOptions(e,r){const n=this.customStrengthOptions.minPasswordLength,i=this.customStrengthOptions.maxPasswordLength;n&&(r.meetsMinPasswordLength=e.length>=n),i&&(r.meetsMaxPasswordLength=e.length<=i)}validatePasswordCharacterOptions(e,r){this.updatePasswordCharacterOptionsStatuses(r,!1,!1,!1,!1);let n;for(let i=0;i<e.length;i++)n=e.charAt(i),this.updatePasswordCharacterOptionsStatuses(r,n>="a"&&n<="z",n>="A"&&n<="Z",n>="0"&&n<="9",this.allowedNonAlphanumericCharacters.includes(n))}updatePasswordCharacterOptionsStatuses(e,r,n,i,a){this.customStrengthOptions.containsLowercaseLetter&&(e.containsLowercaseLetter||(e.containsLowercaseLetter=r)),this.customStrengthOptions.containsUppercaseLetter&&(e.containsUppercaseLetter||(e.containsUppercaseLetter=n)),this.customStrengthOptions.containsNumericCharacter&&(e.containsNumericCharacter||(e.containsNumericCharacter=i)),this.customStrengthOptions.containsNonAlphanumericCharacter&&(e.containsNonAlphanumericCharacter||(e.containsNonAlphanumericCharacter=a))}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class bl{constructor(e,r,n,i){this.app=e,this.heartbeatServiceProvider=r,this.appCheckServiceProvider=n,this.config=i,this.currentUser=null,this.emulatorConfig=null,this.operations=Promise.resolve(),this.authStateSubscription=new zn(this),this.idTokenSubscription=new zn(this),this.beforeStateQueue=new _l(this),this.redirectUser=null,this.isProactiveRefreshEnabled=!1,this.EXPECTED_PASSWORD_POLICY_SCHEMA_VERSION=1,this._canInitEmulator=!0,this._isInitialized=!1,this._deleted=!1,this._initializationPromise=null,this._popupRedirectResolver=null,this._errorFactory=us,this._agentRecaptchaConfig=null,this._tenantRecaptchaConfigs={},this._projectPasswordPolicy=null,this._tenantPasswordPolicies={},this._resolvePersistenceManagerAvailable=void 0,this.lastNotifiedUid=void 0,this.languageCode=null,this.tenantId=null,this.settings={appVerificationDisabledForTesting:!1},this.frameworks=[],this.name=e.name,this.clientVersion=i.sdkClientVersion,this._persistenceManagerAvailable=new Promise(a=>this._resolvePersistenceManagerAvailable=a)}_initializeWithPersistence(e,r){return r&&(this._popupRedirectResolver=de(r)),this._initializationPromise=this.queue(async()=>{if(!this._deleted&&(this.persistenceManager=await ze.create(this,e),this._resolvePersistenceManagerAvailable?.(),!this._deleted)){if(this._popupRedirectResolver?._shouldInitProactively)try{await this._popupRedirectResolver._initialize(this)}catch{}await this.initializeCurrentUser(r),this.lastNotifiedUid=this.currentUser?.uid||null,!this._deleted&&(this._isInitialized=!0)}}),this._initializationPromise}async _onStorageEvent(){if(this._deleted)return;const e=await this.assertedPersistence.getCurrentUser();if(!(!this.currentUser&&!e)){if(this.currentUser&&e&&this.currentUser.uid===e.uid){this._currentUser._assign(e),await this.currentUser.getIdToken();return}await this._updateCurrentUser(e,!0)}}async initializeCurrentUserFromIdToken(e){try{const r=await $t(this,{idToken:e}),n=await ne._fromGetAccountInfoResponse(this,r,e);await this.directlySetCurrentUser(n)}catch(r){console.warn("FirebaseServerApp could not login user with provided authIdToken: ",r),await this.directlySetCurrentUser(null)}}async initializeCurrentUser(e){if(le(this.app)){const a=this.app.settings.authIdToken;return a?new Promise(c=>{setTimeout(()=>this.initializeCurrentUserFromIdToken(a).then(c,c))}):this.directlySetCurrentUser(null)}const r=await this.assertedPersistence.getCurrentUser();let n=r,i=!1;if(e&&this.config.authDomain){await this.getOrInitRedirectPersistenceManager();const a=this.redirectUser?._redirectEventId,c=n?._redirectEventId,u=await this.tryRedirectSignIn(e);(!a||a===c)&&u?.user&&(n=u.user,i=!0)}if(!n)return this.directlySetCurrentUser(null);if(!n._redirectEventId){if(i)try{await this.beforeStateQueue.runMiddleware(n)}catch(a){n=r,this._popupRedirectResolver._overrideRedirectResult(this,()=>Promise.reject(a))}return n?this.reloadAndSetCurrentUserOrClear(n):this.directlySetCurrentUser(null)}return M(this._popupRedirectResolver,this,"argument-error"),await this.getOrInitRedirectPersistenceManager(),this.redirectUser&&this.redirectUser._redirectEventId===n._redirectEventId?this.directlySetCurrentUser(n):this.reloadAndSetCurrentUserOrClear(n)}async tryRedirectSignIn(e){let r=null;try{r=await this._popupRedirectResolver._completeRedirectFn(this,e,!0)}catch{await this._setRedirectUser(null)}return r}async reloadAndSetCurrentUserOrClear(e){try{await Ht(e)}catch(r){if(r?.code!=="auth/network-request-failed")return this.directlySetCurrentUser(null)}return this.directlySetCurrentUser(e)}useDeviceLanguage(){this.languageCode=tl()}async _delete(){this._deleted=!0}async updateCurrentUser(e){if(le(this.app))return Promise.reject(Ne(this));const r=e?Le(e):null;return r&&M(r.auth.config.apiKey===this.config.apiKey,this,"invalid-user-token"),this._updateCurrentUser(r&&r._clone(this))}async _updateCurrentUser(e,r=!1){if(!this._deleted)return e&&M(this.tenantId===e.tenantId,this,"tenant-id-mismatch"),r||await this.beforeStateQueue.runMiddleware(e),this.queue(async()=>{await this.directlySetCurrentUser(e),this.notifyAuthListeners()})}async signOut(){return le(this.app)?Promise.reject(Ne(this)):(await this.beforeStateQueue.runMiddleware(null),(this.redirectPersistenceManager||this._popupRedirectResolver)&&await this._setRedirectUser(null),this._updateCurrentUser(null,!0))}setPersistence(e){return le(this.app)?Promise.reject(Ne(this)):this.queue(async()=>{await this.assertedPersistence.setPersistence(de(e))})}_getRecaptchaConfig(){return this.tenantId==null?this._agentRecaptchaConfig:this._tenantRecaptchaConfigs[this.tenantId]}async validatePassword(e){this._getPasswordPolicyInternal()||await this._updatePasswordPolicy();const r=this._getPasswordPolicyInternal();return r.schemaVersion!==this.EXPECTED_PASSWORD_POLICY_SCHEMA_VERSION?Promise.reject(this._errorFactory.create("unsupported-password-policy-schema-version",{})):r.validatePassword(e)}_getPasswordPolicyInternal(){return this.tenantId===null?this._projectPasswordPolicy:this._tenantPasswordPolicies[this.tenantId]}async _updatePasswordPolicy(){const e=await wl(this),r=new vl(e);this.tenantId===null?this._projectPasswordPolicy=r:this._tenantPasswordPolicies[this.tenantId]=r}_getPersistenceType(){return this.assertedPersistence.persistence.type}_getPersistence(){return this.assertedPersistence.persistence}_updateErrorMap(e){this._errorFactory=new vt("auth","Firebase",e())}onAuthStateChanged(e,r,n){return this.registerStateListener(this.authStateSubscription,e,r,n)}beforeAuthStateChanged(e,r){return this.beforeStateQueue.pushCallback(e,r)}onIdTokenChanged(e,r,n){return this.registerStateListener(this.idTokenSubscription,e,r,n)}authStateReady(){return new Promise((e,r)=>{if(this.currentUser)e();else{const n=this.onAuthStateChanged(()=>{n(),e()},r)}})}async revokeAccessToken(e){if(this.currentUser){const r=await this.currentUser.getIdToken(),n={providerId:"apple.com",tokenType:"ACCESS_TOKEN",token:e,idToken:r};this.tenantId!=null&&(n.tenantId=this.tenantId),await pl(this,n)}}toJSON(){return{apiKey:this.config.apiKey,authDomain:this.config.authDomain,appName:this.name,currentUser:this._currentUser?.toJSON()}}async _setRedirectUser(e,r){const n=await this.getOrInitRedirectPersistenceManager(r);return e===null?n.removeCurrentUser():n.setCurrentUser(e)}async getOrInitRedirectPersistenceManager(e){if(!this.redirectPersistenceManager){const r=e&&de(e)||this._popupRedirectResolver;M(r,this,"argument-error"),this.redirectPersistenceManager=await ze.create(this,[de(r._redirectPersistence)],"redirectUser"),this.redirectUser=await this.redirectPersistenceManager.getCurrentUser()}return this.redirectPersistenceManager}async _redirectUserForId(e){return this._isInitialized&&await this.queue(async()=>{}),this._currentUser?._redirectEventId===e?this._currentUser:this.redirectUser?._redirectEventId===e?this.redirectUser:null}async _persistUserIfCurrent(e){if(e===this.currentUser)return this.queue(async()=>this.directlySetCurrentUser(e))}_notifyListenersIfCurrent(e){e===this.currentUser&&this.notifyAuthListeners()}_key(){return`${this.config.authDomain}:${this.config.apiKey}:${this.name}`}_startProactiveRefresh(){this.isProactiveRefreshEnabled=!0,this.currentUser&&this._currentUser._startProactiveRefresh()}_stopProactiveRefresh(){this.isProactiveRefreshEnabled=!1,this.currentUser&&this._currentUser._stopProactiveRefresh()}get _currentUser(){return this.currentUser}notifyAuthListeners(){if(!this._isInitialized)return;this.idTokenSubscription.next(this.currentUser);const e=this.currentUser?.uid??null;this.lastNotifiedUid!==e&&(this.lastNotifiedUid=e,this.authStateSubscription.next(this.currentUser))}registerStateListener(e,r,n,i){if(this._deleted)return()=>{};const a=typeof r=="function"?r:r.next.bind(r);let c=!1;const u=this._isInitialized?Promise.resolve():this._initializationPromise;if(M(u,this,"internal-error"),u.then(()=>{c||a(this.currentUser)}),typeof r=="function"){const d=e.addObserver(r,n,i);return()=>{c=!0,d()}}else{const d=e.addObserver(r);return()=>{c=!0,d()}}}async directlySetCurrentUser(e){this.currentUser&&this.currentUser!==e&&this._currentUser._stopProactiveRefresh(),e&&this.isProactiveRefreshEnabled&&e._startProactiveRefresh(),this.currentUser=e,e?await this.assertedPersistence.setCurrentUser(e):await this.assertedPersistence.removeCurrentUser()}queue(e){return this.operations=this.operations.then(e,e),this.operations}get assertedPersistence(){return M(this.persistenceManager,this,"internal-error"),this.persistenceManager}_logFramework(e){!e||this.frameworks.includes(e)||(this.frameworks.push(e),this.frameworks.sort(),this.clientVersion=Ts(this.config.clientPlatform,this._getFrameworks()))}_getFrameworks(){return this.frameworks}async _getAdditionalHeaders(){const e={["X-Client-Version"]:this.clientVersion};this.app.options.appId&&(e["X-Firebase-gmpid"]=this.app.options.appId);const r=await this.heartbeatServiceProvider.getImmediate({optional:!0})?.getHeartbeatsHeader();r&&(e["X-Firebase-Client"]=r);const n=await this._getAppCheckToken();return n&&(e["X-Firebase-AppCheck"]=n),e}async _getAppCheckToken(){if(le(this.app)&&this.app.settings.appCheckToken)return this.app.settings.appCheckToken;const e=await this.appCheckServiceProvider.getImmediate({optional:!0})?.getToken();return e?.error&&Zc(`Error while retrieving App Check token: ${e.error}`),e?.token}}function Wr(t){return Le(t)}class zn{constructor(e){this.auth=e,this.observer=null,this.addObserver=Ua(r=>this.observer=r)}get next(){return M(this.observer,this.auth,"internal-error"),this.observer.next.bind(this.observer)}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */let zr={async loadJS(){throw new Error("Unable to load external scripts")},recaptchaV2Script:"",recaptchaEnterpriseScript:"",gapiScript:""};function Al(t){zr=t}function Il(t){return zr.loadJS(t)}function El(){return zr.gapiScript}function Tl(t){return`__${t}${Math.floor(Math.random()*1e6)}`}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Sl(t,e){const r=os(t,"auth");if(r.isInitialized()){const i=r.getImmediate(),a=r.getOptions();if(Ye(a,e??{}))return i;pe(i,"already-initialized")}return r.initialize({options:e})}function kl(t,e){const r=e?.persistence||[],n=(Array.isArray(r)?r:[r]).map(de);e?.errorMap&&t._updateErrorMap(e.errorMap),t._initializeWithPersistence(n,e?.popupRedirectResolver)}function xl(t,e,r){const n=Wr(t);M(/^https?:\/\//.test(e),n,"invalid-emulator-scheme");const i=!!r?.disableWarnings,a=Ss(e),{host:c,port:u}=Cl(e),d=u===null?"":`:${u}`,f={url:`${a}//${c}${d}/`},w=Object.freeze({host:c,port:u,protocol:a.replace(":",""),options:Object.freeze({disableWarnings:i})});if(!n._canInitEmulator){M(n.config.emulator&&n.emulatorConfig,n,"emulator-config-failed"),M(Ye(f,n.config.emulator)&&Ye(w,n.emulatorConfig),n,"emulator-config-failed");return}n.config.emulator=f,n.emulatorConfig=w,n.settings.appVerificationDisabledForTesting=!0,Ur(c)?$a(`${a}//${c}${d}`):i||Pl()}function Ss(t){const e=t.indexOf(":");return e<0?"":t.substr(0,e+1)}function Cl(t){const e=Ss(t),r=/(\/\/)?([^?#/]+)/.exec(t.substr(e.length));if(!r)return{host:"",port:null};const n=r[2].split("@").pop()||"",i=/^(\[[^\]]+\])(:|$)/.exec(n);if(i){const a=i[1];return{host:a,port:Gn(n.substr(a.length+1))}}else{const[a,c]=n.split(":");return{host:a,port:Gn(c)}}}function Gn(t){if(!t)return null;const e=Number(t);return isNaN(e)?null:e}function Pl(){function t(){const e=document.createElement("p"),r=e.style;e.innerText="Running in emulator mode. Do not use with production credentials.",r.position="fixed",r.width="100%",r.backgroundColor="#ffffff",r.border=".1em solid #000000",r.color="#b50000",r.bottom="0px",r.left="0px",r.margin="0px",r.zIndex="10000",r.textAlign="center",e.classList.add("firebase-emulator-warning"),document.body.appendChild(e)}typeof console<"u"&&typeof console.info=="function"&&console.info("WARNING: You are using the Auth Emulator, which is intended for local testing only.  Do not use with production credentials."),typeof window<"u"&&typeof document<"u"&&(document.readyState==="loading"?window.addEventListener("DOMContentLoaded",t):t())}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class ks{constructor(e,r){this.providerId=e,this.signInMethod=r}toJSON(){return he("not implemented")}_getIdTokenResponse(e){return he("not implemented")}_linkToIdToken(e,r){return he("not implemented")}_getReauthenticationResolver(e){return he("not implemented")}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function Ge(t,e){return sl(t,"POST","/v1/accounts:signInWithIdp",Hr(t,e))}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Rl="http://localhost";class De extends ks{constructor(){super(...arguments),this.pendingToken=null}static _fromParams(e){const r=new De(e.providerId,e.signInMethod);return e.idToken||e.accessToken?(e.idToken&&(r.idToken=e.idToken),e.accessToken&&(r.accessToken=e.accessToken),e.nonce&&!e.pendingToken&&(r.nonce=e.nonce),e.pendingToken&&(r.pendingToken=e.pendingToken)):e.oauthToken&&e.oauthTokenSecret?(r.accessToken=e.oauthToken,r.secret=e.oauthTokenSecret):pe("argument-error"),r}toJSON(){return{idToken:this.idToken,accessToken:this.accessToken,secret:this.secret,nonce:this.nonce,pendingToken:this.pendingToken,providerId:this.providerId,signInMethod:this.signInMethod}}static fromJSON(e){const r=typeof e=="string"?JSON.parse(e):e,{providerId:n,signInMethod:i,...a}=r;if(!n||!i)return null;const c=new De(n,i);return c.idToken=a.idToken||void 0,c.accessToken=a.accessToken||void 0,c.secret=a.secret,c.nonce=a.nonce,c.pendingToken=a.pendingToken||null,c}_getIdTokenResponse(e){const r=this.buildRequest();return Ge(e,r)}_linkToIdToken(e,r){const n=this.buildRequest();return n.idToken=r,Ge(e,n)}_getReauthenticationResolver(e){const r=this.buildRequest();return r.autoCreate=!1,Ge(e,r)}buildRequest(){const e={requestUri:Rl,returnSecureToken:!0};if(this.pendingToken)e.pendingToken=this.pendingToken;else{const r={};this.idToken&&(r.id_token=this.idToken),this.accessToken&&(r.access_token=this.accessToken),this.secret&&(r.oauth_token_secret=this.secret),r.providerId=this.providerId,this.nonce&&!this.pendingToken&&(r.nonce=this.nonce),e.postBody=bt(r)}return e}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class xs{constructor(e){this.providerId=e,this.defaultLanguageCode=null,this.customParameters={}}setDefaultLanguage(e){this.defaultLanguageCode=e}setCustomParameters(e){return this.customParameters=e,this}getCustomParameters(){return this.customParameters}}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Et extends xs{constructor(){super(...arguments),this.scopes=[]}addScope(e){return this.scopes.includes(e)||this.scopes.push(e),this}getScopes(){return[...this.scopes]}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class we extends Et{constructor(){super("facebook.com")}static credential(e){return De._fromParams({providerId:we.PROVIDER_ID,signInMethod:we.FACEBOOK_SIGN_IN_METHOD,accessToken:e})}static credentialFromResult(e){return we.credentialFromTaggedObject(e)}static credentialFromError(e){return we.credentialFromTaggedObject(e.customData||{})}static credentialFromTaggedObject({_tokenResponse:e}){if(!e||!("oauthAccessToken"in e)||!e.oauthAccessToken)return null;try{return we.credential(e.oauthAccessToken)}catch{return null}}}we.FACEBOOK_SIGN_IN_METHOD="facebook.com";we.PROVIDER_ID="facebook.com";/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class ye extends Et{constructor(){super("google.com"),this.addScope("profile")}static credential(e,r){return De._fromParams({providerId:ye.PROVIDER_ID,signInMethod:ye.GOOGLE_SIGN_IN_METHOD,idToken:e,accessToken:r})}static credentialFromResult(e){return ye.credentialFromTaggedObject(e)}static credentialFromError(e){return ye.credentialFromTaggedObject(e.customData||{})}static credentialFromTaggedObject({_tokenResponse:e}){if(!e)return null;const{oauthIdToken:r,oauthAccessToken:n}=e;if(!r&&!n)return null;try{return ye.credential(r,n)}catch{return null}}}ye.GOOGLE_SIGN_IN_METHOD="google.com";ye.PROVIDER_ID="google.com";/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class ve extends Et{constructor(){super("github.com")}static credential(e){return De._fromParams({providerId:ve.PROVIDER_ID,signInMethod:ve.GITHUB_SIGN_IN_METHOD,accessToken:e})}static credentialFromResult(e){return ve.credentialFromTaggedObject(e)}static credentialFromError(e){return ve.credentialFromTaggedObject(e.customData||{})}static credentialFromTaggedObject({_tokenResponse:e}){if(!e||!("oauthAccessToken"in e)||!e.oauthAccessToken)return null;try{return ve.credential(e.oauthAccessToken)}catch{return null}}}ve.GITHUB_SIGN_IN_METHOD="github.com";ve.PROVIDER_ID="github.com";/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class be extends Et{constructor(){super("twitter.com")}static credential(e,r){return De._fromParams({providerId:be.PROVIDER_ID,signInMethod:be.TWITTER_SIGN_IN_METHOD,oauthToken:e,oauthTokenSecret:r})}static credentialFromResult(e){return be.credentialFromTaggedObject(e)}static credentialFromError(e){return be.credentialFromTaggedObject(e.customData||{})}static credentialFromTaggedObject({_tokenResponse:e}){if(!e)return null;const{oauthAccessToken:r,oauthTokenSecret:n}=e;if(!r||!n)return null;try{return be.credential(r,n)}catch{return null}}}be.TWITTER_SIGN_IN_METHOD="twitter.com";be.PROVIDER_ID="twitter.com";/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Ze{constructor(e){this.user=e.user,this.providerId=e.providerId,this._tokenResponse=e._tokenResponse,this.operationType=e.operationType}static async _fromIdTokenResponse(e,r,n,i=!1){const a=await ne._fromIdTokenResponse(e,n,i),c=qn(n);return new Ze({user:a,providerId:c,_tokenResponse:n,operationType:r})}static async _forOperation(e,r,n){await e._updateTokensIfNecessary(n,!0);const i=qn(n);return new Ze({user:e,providerId:i,_tokenResponse:n,operationType:r})}}function qn(t){return t.providerId?t.providerId:"phoneNumber"in t?"phone":null}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Vt extends Se{constructor(e,r,n,i){super(r.code,r.message),this.operationType=n,this.user=i,Object.setPrototypeOf(this,Vt.prototype),this.customData={appName:e.name,tenantId:e.tenantId??void 0,_serverResponse:r.customData._serverResponse,operationType:n}}static _fromErrorAndOperation(e,r,n,i){return new Vt(e,r,n,i)}}function Cs(t,e,r,n){return(e==="reauthenticate"?r._getReauthenticationResolver(t):r._getIdTokenResponse(t)).catch(a=>{throw a.code==="auth/multi-factor-auth-required"?Vt._fromErrorAndOperation(t,a,e,n):a})}async function Ol(t,e,r=!1){const n=await wt(t,e._linkToIdToken(t.auth,await t.getIdToken()),r);return Ze._forOperation(t,"link",n)}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function Nl(t,e,r=!1){const{auth:n}=t;if(le(n.app))return Promise.reject(Ne(n));const i="reauthenticate";try{const a=await wt(t,Cs(n,i,e,t),r);M(a.idToken,n,"internal-error");const c=Vr(a.idToken);M(c,n,"internal-error");const{sub:u}=c;return M(t.uid===u,n,"user-mismatch"),Ze._forOperation(t,i,a)}catch(a){throw a?.code==="auth/user-not-found"&&pe(n,"user-mismatch"),a}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function Dl(t,e,r=!1){if(le(t.app))return Promise.reject(Ne(t));const n="signIn",i=await Cs(t,n,e),a=await Ze._fromIdTokenResponse(t,n,i);return r||await t._updateCurrentUser(a.user),a}function Ml(t,e,r,n){return Le(t).onIdTokenChanged(e,r,n)}function Ll(t,e,r){return Le(t).beforeAuthStateChanged(e,r)}function Ul(t){return Le(t).signOut()}const jt="__sak";/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Ps{constructor(e,r){this.storageRetriever=e,this.type=r}_isAvailable(){try{return this.storage?(this.storage.setItem(jt,"1"),this.storage.removeItem(jt),Promise.resolve(!0)):Promise.resolve(!1)}catch{return Promise.resolve(!1)}}_set(e,r){return this.storage.setItem(e,JSON.stringify(r)),Promise.resolve()}_get(e){const r=this.storage.getItem(e);return Promise.resolve(r?JSON.parse(r):null)}_remove(e){return this.storage.removeItem(e),Promise.resolve()}get storage(){return this.storageRetriever()}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Bl=1e3,Fl=10;class Rs extends Ps{constructor(){super(()=>window.localStorage,"LOCAL"),this.boundEventHandler=(e,r)=>this.onStorageEvent(e,r),this.listeners={},this.localCache={},this.pollTimer=null,this.fallbackToPolling=Es(),this._shouldAllowMigration=!0}forAllChangedKeys(e){for(const r of Object.keys(this.listeners)){const n=this.storage.getItem(r),i=this.localCache[r];n!==i&&e(r,i,n)}}onStorageEvent(e,r=!1){if(!e.key){this.forAllChangedKeys((c,u,d)=>{this.notifyListeners(c,d)});return}const n=e.key;r?this.detachListener():this.stopPolling();const i=()=>{const c=this.storage.getItem(n);!r&&this.localCache[n]===c||this.notifyListeners(n,c)},a=this.storage.getItem(n);ml()&&a!==e.newValue&&e.newValue!==e.oldValue?setTimeout(i,Fl):i()}notifyListeners(e,r){this.localCache[e]=r;const n=this.listeners[e];if(n)for(const i of Array.from(n))i(r&&JSON.parse(r))}startPolling(){this.stopPolling(),this.pollTimer=setInterval(()=>{this.forAllChangedKeys((e,r,n)=>{this.onStorageEvent(new StorageEvent("storage",{key:e,oldValue:r,newValue:n}),!0)})},Bl)}stopPolling(){this.pollTimer&&(clearInterval(this.pollTimer),this.pollTimer=null)}attachListener(){window.addEventListener("storage",this.boundEventHandler)}detachListener(){window.removeEventListener("storage",this.boundEventHandler)}_addListener(e,r){Object.keys(this.listeners).length===0&&(this.fallbackToPolling?this.startPolling():this.attachListener()),this.listeners[e]||(this.listeners[e]=new Set,this.localCache[e]=this.storage.getItem(e)),this.listeners[e].add(r)}_removeListener(e,r){this.listeners[e]&&(this.listeners[e].delete(r),this.listeners[e].size===0&&delete this.listeners[e]),Object.keys(this.listeners).length===0&&(this.detachListener(),this.stopPolling())}async _set(e,r){await super._set(e,r),this.localCache[e]=JSON.stringify(r)}async _get(e){const r=await super._get(e);return this.localCache[e]=JSON.stringify(r),r}async _remove(e){await super._remove(e),delete this.localCache[e]}}Rs.type="LOCAL";const $l=Rs;/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Os extends Ps{constructor(){super(()=>window.sessionStorage,"SESSION")}_addListener(e,r){}_removeListener(e,r){}}Os.type="SESSION";const Ns=Os;/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Hl(t){return Promise.all(t.map(async e=>{try{return{fulfilled:!0,value:await e}}catch(r){return{fulfilled:!1,reason:r}}}))}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Yt{constructor(e){this.eventTarget=e,this.handlersMap={},this.boundEventHandler=this.handleEvent.bind(this)}static _getInstance(e){const r=this.receivers.find(i=>i.isListeningto(e));if(r)return r;const n=new Yt(e);return this.receivers.push(n),n}isListeningto(e){return this.eventTarget===e}async handleEvent(e){const r=e,{eventId:n,eventType:i,data:a}=r.data,c=this.handlersMap[i];if(!c?.size)return;r.ports[0].postMessage({status:"ack",eventId:n,eventType:i});const u=Array.from(c).map(async f=>f(r.origin,a)),d=await Hl(u);r.ports[0].postMessage({status:"done",eventId:n,eventType:i,response:d})}_subscribe(e,r){Object.keys(this.handlersMap).length===0&&this.eventTarget.addEventListener("message",this.boundEventHandler),this.handlersMap[e]||(this.handlersMap[e]=new Set),this.handlersMap[e].add(r)}_unsubscribe(e,r){this.handlersMap[e]&&r&&this.handlersMap[e].delete(r),(!r||this.handlersMap[e].size===0)&&delete this.handlersMap[e],Object.keys(this.handlersMap).length===0&&this.eventTarget.removeEventListener("message",this.boundEventHandler)}}Yt.receivers=[];/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Gr(t="",e=10){let r="";for(let n=0;n<e;n++)r+=Math.floor(Math.random()*10);return t+r}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Vl{constructor(e){this.target=e,this.handlers=new Set}removeMessageHandler(e){e.messageChannel&&(e.messageChannel.port1.removeEventListener("message",e.onMessage),e.messageChannel.port1.close()),this.handlers.delete(e)}async _send(e,r,n=50){const i=typeof MessageChannel<"u"?new MessageChannel:null;if(!i)throw new Error("connection_unavailable");let a,c;return new Promise((u,d)=>{const f=Gr("",20);i.port1.start();const w=setTimeout(()=>{d(new Error("unsupported_event"))},n);c={messageChannel:i,onMessage(g){const E=g;if(E.data.eventId===f)switch(E.data.status){case"ack":clearTimeout(w),a=setTimeout(()=>{d(new Error("timeout"))},3e3);break;case"done":clearTimeout(a),u(E.data.response);break;default:clearTimeout(w),clearTimeout(a),d(new Error("invalid_response"));break}}},this.handlers.add(c),i.port1.addEventListener("message",c.onMessage),this.target.postMessage({eventType:e,eventId:f,data:r},[i.port2])}).finally(()=>{c&&this.removeMessageHandler(c)})}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function ae(){return window}function jl(t){ae().location.href=t}/**
 * @license
 * Copyright 2020 Google LLC.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Ds(){return typeof ae().WorkerGlobalScope<"u"&&typeof ae().importScripts=="function"}async function Wl(){if(!navigator?.serviceWorker)return null;try{return(await navigator.serviceWorker.ready).active}catch{return null}}function zl(){return navigator?.serviceWorker?.controller||null}function Gl(){return Ds()?self:null}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Ms="firebaseLocalStorageDb",ql=1,Wt="firebaseLocalStorage",Ls="fbase_key";class Tt{constructor(e){this.request=e}toPromise(){return new Promise((e,r)=>{this.request.addEventListener("success",()=>{e(this.request.result)}),this.request.addEventListener("error",()=>{r(this.request.error)})})}}function Xt(t,e){return t.transaction([Wt],e?"readwrite":"readonly").objectStore(Wt)}function Kl(){const t=indexedDB.deleteDatabase(Ms);return new Tt(t).toPromise()}function Sr(){const t=indexedDB.open(Ms,ql);return new Promise((e,r)=>{t.addEventListener("error",()=>{r(t.error)}),t.addEventListener("upgradeneeded",()=>{const n=t.result;try{n.createObjectStore(Wt,{keyPath:Ls})}catch(i){r(i)}}),t.addEventListener("success",async()=>{const n=t.result;n.objectStoreNames.contains(Wt)?e(n):(n.close(),await Kl(),e(await Sr()))})})}async function Kn(t,e,r){const n=Xt(t,!0).put({[Ls]:e,value:r});return new Tt(n).toPromise()}async function Jl(t,e){const r=Xt(t,!1).get(e),n=await new Tt(r).toPromise();return n===void 0?null:n.value}function Jn(t,e){const r=Xt(t,!0).delete(e);return new Tt(r).toPromise()}const Yl=800,Xl=3;class Us{constructor(){this.type="LOCAL",this._shouldAllowMigration=!0,this.listeners={},this.localCache={},this.pollTimer=null,this.pendingWrites=0,this.receiver=null,this.sender=null,this.serviceWorkerReceiverAvailable=!1,this.activeServiceWorker=null,this._workerInitializationPromise=this.initializeServiceWorkerMessaging().then(()=>{},()=>{})}async _openDb(){return this.db?this.db:(this.db=await Sr(),this.db)}async _withRetries(e){let r=0;for(;;)try{const n=await this._openDb();return await e(n)}catch(n){if(r++>Xl)throw n;this.db&&(this.db.close(),this.db=void 0)}}async initializeServiceWorkerMessaging(){return Ds()?this.initializeReceiver():this.initializeSender()}async initializeReceiver(){this.receiver=Yt._getInstance(Gl()),this.receiver._subscribe("keyChanged",async(e,r)=>({keyProcessed:(await this._poll()).includes(r.key)})),this.receiver._subscribe("ping",async(e,r)=>["keyChanged"])}async initializeSender(){if(this.activeServiceWorker=await Wl(),!this.activeServiceWorker)return;this.sender=new Vl(this.activeServiceWorker);const e=await this.sender._send("ping",{},800);!e||e[0]?.fulfilled&&e[0]?.value.includes("keyChanged")&&(this.serviceWorkerReceiverAvailable=!0)}async notifyServiceWorker(e){if(!(!this.sender||!this.activeServiceWorker||zl()!==this.activeServiceWorker))try{await this.sender._send("keyChanged",{key:e},this.serviceWorkerReceiverAvailable?800:50)}catch{}}async _isAvailable(){try{if(!indexedDB)return!1;const e=await Sr();return await Kn(e,jt,"1"),await Jn(e,jt),!0}catch{}return!1}async _withPendingWrite(e){this.pendingWrites++;try{await e()}finally{this.pendingWrites--}}async _set(e,r){return this._withPendingWrite(async()=>(await this._withRetries(n=>Kn(n,e,r)),this.localCache[e]=r,this.notifyServiceWorker(e)))}async _get(e){const r=await this._withRetries(n=>Jl(n,e));return this.localCache[e]=r,r}async _remove(e){return this._withPendingWrite(async()=>(await this._withRetries(r=>Jn(r,e)),delete this.localCache[e],this.notifyServiceWorker(e)))}async _poll(){const e=await this._withRetries(i=>{const a=Xt(i,!1).getAll();return new Tt(a).toPromise()});if(!e)return[];if(this.pendingWrites!==0)return[];const r=[],n=new Set;if(e.length!==0)for(const{fbase_key:i,value:a}of e)n.add(i),JSON.stringify(this.localCache[i])!==JSON.stringify(a)&&(this.notifyListeners(i,a),r.push(i));for(const i of Object.keys(this.localCache))this.localCache[i]&&!n.has(i)&&(this.notifyListeners(i,null),r.push(i));return r}notifyListeners(e,r){this.localCache[e]=r;const n=this.listeners[e];if(n)for(const i of Array.from(n))i(r)}startPolling(){this.stopPolling(),this.pollTimer=setInterval(async()=>this._poll(),Yl)}stopPolling(){this.pollTimer&&(clearInterval(this.pollTimer),this.pollTimer=null)}_addListener(e,r){Object.keys(this.listeners).length===0&&this.startPolling(),this.listeners[e]||(this.listeners[e]=new Set,this._get(e)),this.listeners[e].add(r)}_removeListener(e,r){this.listeners[e]&&(this.listeners[e].delete(r),this.listeners[e].size===0&&delete this.listeners[e]),Object.keys(this.listeners).length===0&&this.stopPolling()}}Us.type="LOCAL";const Zl=Us;new It(3e4,6e4);/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function Ql(t,e){return e?de(e):(M(t._popupRedirectResolver,t,"argument-error"),t._popupRedirectResolver)}/**
 * @license
 * Copyright 2019 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class qr extends ks{constructor(e){super("custom","custom"),this.params=e}_getIdTokenResponse(e){return Ge(e,this._buildIdpRequest())}_linkToIdToken(e,r){return Ge(e,this._buildIdpRequest(r))}_getReauthenticationResolver(e){return Ge(e,this._buildIdpRequest())}_buildIdpRequest(e){const r={requestUri:this.params.requestUri,sessionId:this.params.sessionId,postBody:this.params.postBody,tenantId:this.params.tenantId,pendingToken:this.params.pendingToken,returnSecureToken:!0,returnIdpCredential:!0};return e&&(r.idToken=e),r}}function eu(t){return Dl(t.auth,new qr(t),t.bypassAuthState)}function tu(t){const{auth:e,user:r}=t;return M(r,e,"internal-error"),Nl(r,new qr(t),t.bypassAuthState)}async function ru(t){const{auth:e,user:r}=t;return M(r,e,"internal-error"),Ol(r,new qr(t),t.bypassAuthState)}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Bs{constructor(e,r,n,i,a=!1){this.auth=e,this.resolver=n,this.user=i,this.bypassAuthState=a,this.pendingPromise=null,this.eventManager=null,this.filter=Array.isArray(r)?r:[r]}execute(){return new Promise(async(e,r)=>{this.pendingPromise={resolve:e,reject:r};try{this.eventManager=await this.resolver._initialize(this.auth),await this.onExecution(),this.eventManager.registerConsumer(this)}catch(n){this.reject(n)}})}async onAuthEvent(e){const{urlResponse:r,sessionId:n,postBody:i,tenantId:a,error:c,type:u}=e;if(c){this.reject(c);return}const d={auth:this.auth,requestUri:r,sessionId:n,tenantId:a||void 0,postBody:i||void 0,user:this.user,bypassAuthState:this.bypassAuthState};try{this.resolve(await this.getIdpTask(u)(d))}catch(f){this.reject(f)}}onError(e){this.reject(e)}getIdpTask(e){switch(e){case"signInViaPopup":case"signInViaRedirect":return eu;case"linkViaPopup":case"linkViaRedirect":return ru;case"reauthViaPopup":case"reauthViaRedirect":return tu;default:pe(this.auth,"internal-error")}}resolve(e){ge(this.pendingPromise,"Pending promise was never set"),this.pendingPromise.resolve(e),this.unregisterAndCleanUp()}reject(e){ge(this.pendingPromise,"Pending promise was never set"),this.pendingPromise.reject(e),this.unregisterAndCleanUp()}unregisterAndCleanUp(){this.eventManager&&this.eventManager.unregisterConsumer(this),this.pendingPromise=null,this.cleanUp()}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const nu=new It(2e3,1e4);class He extends Bs{constructor(e,r,n,i,a){super(e,r,i,a),this.provider=n,this.authWindow=null,this.pollId=null,He.currentPopupAction&&He.currentPopupAction.cancel(),He.currentPopupAction=this}async executeNotNull(){const e=await this.execute();return M(e,this.auth,"internal-error"),e}async onExecution(){ge(this.filter.length===1,"Popup operations only handle one event");const e=Gr();this.authWindow=await this.resolver._openPopup(this.auth,this.provider,this.filter[0],e),this.authWindow.associatedEvent=e,this.resolver._originValidation(this.auth).catch(r=>{this.reject(r)}),this.resolver._isIframeWebStorageSupported(this.auth,r=>{r||this.reject(oe(this.auth,"web-storage-unsupported"))}),this.pollUserCancellation()}get eventId(){return this.authWindow?.associatedEvent||null}cancel(){this.reject(oe(this.auth,"cancelled-popup-request"))}cleanUp(){this.authWindow&&this.authWindow.close(),this.pollId&&window.clearTimeout(this.pollId),this.authWindow=null,this.pollId=null,He.currentPopupAction=null}pollUserCancellation(){const e=()=>{if(this.authWindow?.window?.closed){this.pollId=window.setTimeout(()=>{this.pollId=null,this.reject(oe(this.auth,"popup-closed-by-user"))},8e3);return}this.pollId=window.setTimeout(e,nu.get())};e()}}He.currentPopupAction=null;/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const iu="pendingRedirect",Ot=new Map;class su extends Bs{constructor(e,r,n=!1){super(e,["signInViaRedirect","linkViaRedirect","reauthViaRedirect","unknown"],r,void 0,n),this.eventId=null}async execute(){let e=Ot.get(this.auth._key());if(!e){try{const n=await ou(this.resolver,this.auth)?await super.execute():null;e=()=>Promise.resolve(n)}catch(r){e=()=>Promise.reject(r)}Ot.set(this.auth._key(),e)}return this.bypassAuthState||Ot.set(this.auth._key(),()=>Promise.resolve(null)),e()}async onAuthEvent(e){if(e.type==="signInViaRedirect")return super.onAuthEvent(e);if(e.type==="unknown"){this.resolve(null);return}if(e.eventId){const r=await this.auth._redirectUserForId(e.eventId);if(r)return this.user=r,super.onAuthEvent(e);this.resolve(null)}}async onExecution(){}cleanUp(){}}async function ou(t,e){const r=lu(e),n=cu(t);if(!await n._isAvailable())return!1;const i=await n._get(r)==="true";return await n._remove(r),i}function au(t,e){Ot.set(t._key(),e)}function cu(t){return de(t._redirectPersistence)}function lu(t){return Rt(iu,t.config.apiKey,t.name)}async function uu(t,e,r=!1){if(le(t.app))return Promise.reject(Ne(t));const n=Wr(t),i=Ql(n,e),c=await new su(n,i,r).execute();return c&&!r&&(delete c.user._redirectEventId,await n._persistUserIfCurrent(c.user),await n._setRedirectUser(null,e)),c}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const hu=10*60*1e3;class du{constructor(e){this.auth=e,this.cachedEventUids=new Set,this.consumers=new Set,this.queuedRedirectEvent=null,this.hasHandledPotentialRedirect=!1,this.lastProcessedEventTime=Date.now()}registerConsumer(e){this.consumers.add(e),this.queuedRedirectEvent&&this.isEventForConsumer(this.queuedRedirectEvent,e)&&(this.sendToConsumer(this.queuedRedirectEvent,e),this.saveEventToCache(this.queuedRedirectEvent),this.queuedRedirectEvent=null)}unregisterConsumer(e){this.consumers.delete(e)}onEvent(e){if(this.hasEventBeenHandled(e))return!1;let r=!1;return this.consumers.forEach(n=>{this.isEventForConsumer(e,n)&&(r=!0,this.sendToConsumer(e,n),this.saveEventToCache(e))}),this.hasHandledPotentialRedirect||!fu(e)||(this.hasHandledPotentialRedirect=!0,r||(this.queuedRedirectEvent=e,r=!0)),r}sendToConsumer(e,r){if(e.error&&!Fs(e)){const n=e.error.code?.split("auth/")[1]||"internal-error";r.onError(oe(this.auth,n))}else r.onAuthEvent(e)}isEventForConsumer(e,r){const n=r.eventId===null||!!e.eventId&&e.eventId===r.eventId;return r.filter.includes(e.type)&&n}hasEventBeenHandled(e){return Date.now()-this.lastProcessedEventTime>=hu&&this.cachedEventUids.clear(),this.cachedEventUids.has(Yn(e))}saveEventToCache(e){this.cachedEventUids.add(Yn(e)),this.lastProcessedEventTime=Date.now()}}function Yn(t){return[t.type,t.eventId,t.sessionId,t.tenantId].filter(e=>e).join("-")}function Fs({type:t,error:e}){return t==="unknown"&&e?.code==="auth/no-auth-event"}function fu(t){switch(t.type){case"signInViaRedirect":case"linkViaRedirect":case"reauthViaRedirect":return!0;case"unknown":return Fs(t);default:return!1}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */async function pu(t,e={}){return it(t,"GET","/v1/projects",e)}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const gu=/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/,mu=/^https?/;async function _u(t){if(t.config.emulator)return;const{authorizedDomains:e}=await pu(t);for(const r of e)try{if(wu(r))return}catch{}pe(t,"unauthorized-domain")}function wu(t){const e=Er(),{protocol:r,hostname:n}=new URL(e);if(t.startsWith("chrome-extension://")){const c=new URL(t);return c.hostname===""&&n===""?r==="chrome-extension:"&&t.replace("chrome-extension://","")===e.replace("chrome-extension://",""):r==="chrome-extension:"&&c.hostname===n}if(!mu.test(r))return!1;if(gu.test(t))return n===t;const i=t.replace(/\./g,"\\.");return new RegExp("^(.+\\."+i+"|"+i+")$","i").test(n)}/**
 * @license
 * Copyright 2020 Google LLC.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const yu=new It(3e4,6e4);function Xn(){const t=ae().___jsl;if(t?.H){for(const e of Object.keys(t.H))if(t.H[e].r=t.H[e].r||[],t.H[e].L=t.H[e].L||[],t.H[e].r=[...t.H[e].L],t.CP)for(let r=0;r<t.CP.length;r++)t.CP[r]=null}}function vu(t){return new Promise((e,r)=>{function n(){Xn(),gapi.load("gapi.iframes",{callback:()=>{e(gapi.iframes.getContext())},ontimeout:()=>{Xn(),r(oe(t,"network-request-failed"))},timeout:yu.get()})}if(ae().gapi?.iframes?.Iframe)e(gapi.iframes.getContext());else if(ae().gapi?.load)n();else{const i=Tl("iframefcb");return ae()[i]=()=>{gapi.load?n():r(oe(t,"network-request-failed"))},Il(`${El()}?onload=${i}`).catch(a=>r(a))}}).catch(e=>{throw Nt=null,e})}let Nt=null;function bu(t){return Nt=Nt||vu(t),Nt}/**
 * @license
 * Copyright 2020 Google LLC.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Au=new It(5e3,15e3),Iu="__/auth/iframe",Eu="emulator/auth/iframe",Tu={style:{position:"absolute",top:"-100px",width:"1px",height:"1px"},"aria-hidden":"true",tabindex:"-1"},Su=new Map([["identitytoolkit.googleapis.com","p"],["staging-identitytoolkit.sandbox.googleapis.com","s"],["test-identitytoolkit.sandbox.googleapis.com","t"]]);function ku(t){const e=t.config;M(e.authDomain,t,"auth-domain-config-required");const r=e.emulator?$r(e,Eu):`https://${t.config.authDomain}/${Iu}`,n={apiKey:e.apiKey,appName:t.name,v:At},i=Su.get(t.config.apiHost);i&&(n.eid=i);const a=t._getFrameworks();return a.length&&(n.fw=a.join(",")),`${r}?${bt(n).slice(1)}`}async function xu(t){const e=await bu(t),r=ae().gapi;return M(r,t,"internal-error"),e.open({where:document.body,url:ku(t),messageHandlersFilter:r.iframes.CROSS_ORIGIN_IFRAMES_FILTER,attributes:Tu,dontclear:!0},n=>new Promise(async(i,a)=>{await n.restyle({setHideOnLeave:!1});const c=oe(t,"network-request-failed"),u=ae().setTimeout(()=>{a(c)},Au.get());function d(){ae().clearTimeout(u),i(n)}n.ping(d).then(d,()=>{a(c)})}))}/**
 * @license
 * Copyright 2020 Google LLC.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Cu={location:"yes",resizable:"yes",statusbar:"yes",toolbar:"no"},Pu=500,Ru=600,Ou="_blank",Nu="http://localhost";class Zn{constructor(e){this.window=e,this.associatedEvent=null}close(){if(this.window)try{this.window.close()}catch{}}}function Du(t,e,r,n=Pu,i=Ru){const a=Math.max((window.screen.availHeight-i)/2,0).toString(),c=Math.max((window.screen.availWidth-n)/2,0).toString();let u="";const d={...Cu,width:n.toString(),height:i.toString(),top:a,left:c},f=ee().toLowerCase();r&&(u=ys(f)?Ou:r),_s(f)&&(e=e||Nu,d.scrollbars="yes");const w=Object.entries(d).reduce((E,[b,P])=>`${E}${b}=${P},`,"");if(gl(f)&&u!=="_self")return Mu(e||"",u),new Zn(null);const g=window.open(e||"",u,w);M(g,t,"popup-blocked");try{g.focus()}catch{}return new Zn(g)}function Mu(t,e){const r=document.createElement("a");r.href=t,r.target=e;const n=document.createEvent("MouseEvent");n.initMouseEvent("click",!0,!0,window,1,0,0,0,0,!1,!1,!1,!1,1,null),r.dispatchEvent(n)}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const Lu="__/auth/handler",Uu="emulator/auth/handler",Bu=encodeURIComponent("fac");async function Qn(t,e,r,n,i,a){M(t.config.authDomain,t,"auth-domain-config-required"),M(t.config.apiKey,t,"invalid-api-key");const c={apiKey:t.config.apiKey,appName:t.name,authType:r,redirectUrl:n,v:At,eventId:i};if(e instanceof xs){e.setDefaultLanguage(t.languageCode),c.providerId=e.providerId||"",La(e.getCustomParameters())||(c.customParameters=JSON.stringify(e.getCustomParameters()));for(const[w,g]of Object.entries(a||{}))c[w]=g}if(e instanceof Et){const w=e.getScopes().filter(g=>g!=="");w.length>0&&(c.scopes=w.join(","))}t.tenantId&&(c.tid=t.tenantId);const u=c;for(const w of Object.keys(u))u[w]===void 0&&delete u[w];const d=await t._getAppCheckToken(),f=d?`#${Bu}=${encodeURIComponent(d)}`:"";return`${Fu(t)}?${bt(u).slice(1)}${f}`}function Fu({config:t}){return t.emulator?$r(t,Uu):`https://${t.authDomain}/${Lu}`}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const ur="webStorageSupport";class $u{constructor(){this.eventManagers={},this.iframes={},this.originValidationPromises={},this._redirectPersistence=Ns,this._completeRedirectFn=uu,this._overrideRedirectResult=au}async _openPopup(e,r,n,i){ge(this.eventManagers[e._key()]?.manager,"_initialize() not called before _openPopup()");const a=await Qn(e,r,n,Er(),i);return Du(e,a,Gr())}async _openRedirect(e,r,n,i){await this._originValidation(e);const a=await Qn(e,r,n,Er(),i);return jl(a),new Promise(()=>{})}_initialize(e){const r=e._key();if(this.eventManagers[r]){const{manager:i,promise:a}=this.eventManagers[r];return i?Promise.resolve(i):(ge(a,"If manager is not set, promise should be"),a)}const n=this.initAndGetManager(e);return this.eventManagers[r]={promise:n},n.catch(()=>{delete this.eventManagers[r]}),n}async initAndGetManager(e){const r=await xu(e),n=new du(e);return r.register("authEvent",i=>(M(i?.authEvent,e,"invalid-auth-event"),{status:n.onEvent(i.authEvent)?"ACK":"ERROR"}),gapi.iframes.CROSS_ORIGIN_IFRAMES_FILTER),this.eventManagers[e._key()]={manager:n},this.iframes[e._key()]=r,n}_isIframeWebStorageSupported(e,r){this.iframes[e._key()].send(ur,{type:ur},i=>{const a=i?.[0]?.[ur];a!==void 0&&r(!!a),pe(e,"internal-error")},gapi.iframes.CROSS_ORIGIN_IFRAMES_FILTER)}_originValidation(e){const r=e._key();return this.originValidationPromises[r]||(this.originValidationPromises[r]=_u(e)),this.originValidationPromises[r]}get _shouldInitProactively(){return Es()||ws()||jr()}}const Hu=$u;var ei="@firebase/auth",ti="1.12.2";/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */class Vu{constructor(e){this.auth=e,this.internalListeners=new Map}getUid(){return this.assertAuthConfigured(),this.auth.currentUser?.uid||null}async getToken(e){return this.assertAuthConfigured(),await this.auth._initializationPromise,this.auth.currentUser?{accessToken:await this.auth.currentUser.getIdToken(e)}:null}addAuthTokenListener(e){if(this.assertAuthConfigured(),this.internalListeners.has(e))return;const r=this.auth.onIdTokenChanged(n=>{e(n?.stsTokenManager.accessToken||null)});this.internalListeners.set(e,r),this.updateProactiveRefresh()}removeAuthTokenListener(e){this.assertAuthConfigured();const r=this.internalListeners.get(e);!r||(this.internalListeners.delete(e),r(),this.updateProactiveRefresh())}assertAuthConfigured(){M(this.auth._initializationPromise,"dependent-sdk-initialized-before-auth")}updateProactiveRefresh(){this.internalListeners.size>0?this.auth._startProactiveRefresh():this.auth._stopProactiveRefresh()}}/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */function ju(t){switch(t){case"Node":return"node";case"ReactNative":return"rn";case"Worker":return"webworker";case"Cordova":return"cordova";case"WebExtension":return"web-extension";default:return}}function Wu(t){mt(new Xe("auth",(e,{options:r})=>{const n=e.getProvider("app").getImmediate(),i=e.getProvider("heartbeat"),a=e.getProvider("app-check-internal"),{apiKey:c,authDomain:u}=n.options;M(c&&!c.includes(":"),"invalid-api-key",{appName:n.name});const d={apiKey:c,authDomain:u,clientPlatform:t,apiHost:"identitytoolkit.googleapis.com",tokenApiHost:"securetoken.googleapis.com",apiScheme:"https",sdkClientVersion:Ts(t)},f=new bl(n,i,a,d);return kl(f,r),f},"PUBLIC").setInstantiationMode("EXPLICIT").setInstanceCreatedCallback((e,r,n)=>{e.getProvider("auth-internal").initialize()})),mt(new Xe("auth-internal",e=>{const r=Wr(e.getProvider("auth").getImmediate());return(n=>new Vu(n))(r)},"PRIVATE").setInstantiationMode("EXPLICIT")),je(ei,ti,ju(t)),je(ei,ti,"esm2020")}/**
 * @license
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */const zu=5*60,Gu=rs("authIdTokenMaxAge")||zu;let ri=null;const qu=t=>async e=>{const r=e&&await e.getIdTokenResult(),n=r&&(new Date().getTime()-Date.parse(r.issuedAtTime))/1e3;if(n&&n>Gu)return;const i=r?.token;ri!==i&&(ri=i,await fetch(t,{method:i?"POST":"DELETE",headers:i?{Authorization:`Bearer ${i}`}:{}}))};function sd(t=$c()){const e=os(t,"auth");if(e.isInitialized())return e.getImmediate();const r=Sl(t,{popupRedirectResolver:Hu,persistence:[Zl,$l,Ns]}),n=rs("authTokenSyncURL");if(n&&typeof isSecureContext=="boolean"&&isSecureContext){const a=new URL(n,location.origin);if(location.origin===a.origin){const c=qu(a.toString());Ll(r,c,()=>c(r.currentUser)),Ml(r,u=>c(u))}}const i=Ea("auth");return i&&xl(r,`http://${i}`),r}function Ku(){return document.getElementsByTagName("head")?.[0]??document}Al({loadJS(t){return new Promise((e,r)=>{const n=document.createElement("script");n.setAttribute("src",t),n.onload=e,n.onerror=i=>{const a=oe("internal-error");a.customData=i,r(a)},n.type="text/javascript",n.charset="UTF-8",Ku().appendChild(n)})},gapiScript:"https://apis.google.com/js/api.js",recaptchaV2Script:"https://www.google.com/recaptcha/api.js",recaptchaEnterpriseScript:"https://www.google.com/recaptcha/enterprise.js?render="});Wu("Browser");const Kr=void 0,Jr="oauth-token",Oe=tt(null),Ju=tt(!1);tt(!1);let Yu;function od(){return Yu??=pa(Jr).then(t=>{t&&(Oe.value=JSON.parse(t))}).catch(t=>void Ee.exception("loadState",t)).finally(()=>{Ju.value=!0})}function Xu(){Oe.value=null,Ki(Jr,void 0)}const Zu=10*60*1e3,ni=2e3,Fe=tt(null);let at;async function Qu(t,e,r){return await eh(t,e,r).then(n=>th(t,n))}async function eh(t,e,r){if(Fe.value&&at)return console.debug("Already authenticating, triggering callback again"),e(Fe.value.state),at;const n=await Qe(t+"/flow",{method:"POST"});if(!n.ok)throw new Error(await n.text());Fe.value=await n.json(),e(Fe.value.state);let i,a;return at=new Promise((c,u)=>{let d=!1;const f=()=>{d=!0,clearTimeout(a),clearTimeout(i)};if(r?.aborted)return f(),u("login canceled");i=setTimeout(()=>{f(),Ee.exception("login",new Error("login timeout")),u("login timeout")},Zu);const w=async()=>{let g;try{g=await rh(t,Fe.value.id)}catch(E){console.debug("login poll failed, retrying",E)}if(!d){if(g)return f(),c(g);a=setTimeout(w,ni)}};a=setTimeout(w,ni),r?.addEventListener("abort",()=>{f(),u("login canceled")})}).catch(c=>{throw console.error(c),c.message&&(Ee.exception("login",c),Ji(c.message,{error:!0})),c}).finally(()=>{Fe.value=null,at=void 0,clearTimeout(a),clearTimeout(i)}),at}async function $s(t){return Oe.value=t,await Ki(Jr,JSON.stringify(t)).catch(e=>{Ee.exception("saveLoginToken:persist",e),Ji("Signed in, but your session couldn't be saved \u2014 it may not survive a restart")}),t}const th=async(t,e)=>{const r=await Qe(t+"/token",{headers:{...Kr,"Content-Type":"application/json"},method:"POST",tracking:"error",body:JSON.stringify(e)});if(r.ok)return await r.json();throw new Error(await r.text())},rh=async(t,e)=>{console.debug("fetch auth result");const r=await Qe(t+"/flow/"+e,{headers:Kr,tracking:"error"});switch(r.status){case 204:return;case 200:return await r.json();default:throw new Error(await r.text())}},nh=60;async function ad({auth:t,authURL:e,force:r}){if(!Oe.value)return;const{refresh_token:n,expires_in:i,created_at:a}=Oe.value,c=ga();if(r||a+i<c+nh){const u=await Qe(e+"/refresh",{headers:Kr,method:"POST",body:JSON.stringify({refresh_token:n})});if(u.ok){const d=await u.json(),f={...Oe.value,...d,created_at:c};return $s(f)}else{Ee.exception("getValidToken:refresh",void 0,await u.text()),t&&await Ul(t).catch(console.error),Xu();return}}return Oe.value}const Yr="x-transport-error",ih=6e5,sh=2e3,oh=3e4;async function ah(t,e){const r=new Headers(e?.headers);r.set("prefer","respond-async");const n=await Qe(t,{...e,headers:r}).catch(i=>new Response(i?.message||"Request failed",{status:500,headers:{[Yr]:"1"}}));return n.status===202&&!n.headers.get("location")?new Response("Missing response location",{status:502}):n}async function ch(t,e){if(t.status===202){const r=t.headers.get("location");if(r)return lh(r,e)}return t}async function lh(t,e){const r=e?.pollTimeout??ih,n=e?.pollInterval??(()=>sh),i=e?.transportGiveUp??oh,a=Date.now()+r;let c;console.debug("polling started",{url:t,timeout:r,deadline:a});for(let u=0;;u++){await new Promise(f=>setTimeout(f,n(u)));const d=a-Date.now();if(d<=0)return new Response("Timeout",{status:504});try{const f=AbortSignal.timeout(d),w=await fetch(t,{method:"HEAD",signal:f});if(c=void 0,w.status===200){const g=await Qe(t,{signal:f});if(g.headers.get("content-type")?.includes("application/problem+json")){const{status:b=500}=await g.clone().json();return new Response(g.body,{status:b,headers:g.headers})}return g}}catch(f){if(f?.name==="TimeoutError")return new Response("Timeout",{status:504});if(c??=Date.now(),Date.now()-c>=i)return new Response(f?.message||"Request failed",{status:504,headers:{[Yr]:"1"}});console.debug("polling attempt",u+1,"error:",f?.message)}}}const ii=()=>{const t=globalThis.location?.hostname;return t==="localhost"||/^127(?:\.\d{1,3}){3}$/.test(t??"")?t:uh()||"localhost"};function uh(){try{return}catch{return}}const Hs=!!{}.STAGING||!1,Vs=Hs?"https://us-central1-dev-shared-services.cloudfunctions.net/api":"https://api.divriots.com",cd=`${Vs}/v1`,hh=Hs?"https://dev-divriots-f6c83.web.app":"https://divriots.com",ld=function(t,e,r,n,i){const a=async(c,u,d)=>{if(u.body&&(u.method==="POST"||u.method==="PUT")&&(Or(u.body)||0)>32*Math.pow(2,20))throw new Error("Payload is too large");const f=new Headers(u.headers),w=(d??await r)?.id_token;w&&(f.set("authorization",`Bearer ${w}`),n&&f.set("x-drc-figma-uid",n),u.headers=f);const g=await ah(`${t}/${c}`,u).then(b=>ch(b,i?.pollOpts)).catch(()=>{throw new Error("Unexpected error. Please contact us for support.")});if(g.ok)return g;if(g.status===401&&w&&!d&&i?.refreshAuth){const b=await i.refreshAuth();if(b)return a(c,u,b)}const E=await g.clone().json().then(b=>b.message||b.error||JSON.stringify(b)).catch(()=>g.text());throw g.headers.get(Yr)?new Error(`Couldn't reach our servers (${E}). Please check your connection and try again in a few minutes.`):new Error(E)};return{get:c=>a(`${e}/${c}`,{method:"GET"}).then(ct),delete:(c,u)=>a(`${e}/${c}`,{method:"DELETE",...u?{headers:{"Content-Type":"application/json"},body:JSON.stringify(u)}:{}}),list:()=>a(e,{method:"GET"}).then(ct),put:(c,u)=>a(`${e}/${c}`,{method:"PUT",headers:{"Content-Type":"application/json"},body:JSON.stringify(u)}).then(ct),post:c=>a(e,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(c)}).then(ct),patch:c=>a(e,{method:"PATCH",headers:{"Content-Type":"application/json"},body:JSON.stringify(c)}).then(ct)}};async function ct(t){if(!t.headers.get("Content-Type")?.toLowerCase().includes("application/json"))return t.text();const r=await t.json(),n=t.headers.get("X-Credits-Consumed")||void 0,i=t.headers.get("X-Images-Generated")||void 0;return Object.prototype.toString.call(r)==="[object Object]"?{...r,_creditsConsumed:n,_imagesGenerated:i}:r}var dh="firebase",fh="12.11.0";/**
 * @license
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */je(dh,fh,"app");function ph(t){const e=globalThis.chrome;e?.tabs?.create?e.tabs.create({url:t}):window.self!==window.top?window.open(t,"_blank","noopener,noreferrer"):window.location.href=t}async function ud(t){await Qu(`${Vs}/oauth`,e=>{const r=new URL("/login",hh);r.searchParams.set("state",e),t&&r.searchParams.set("redirect",t),ph(r.toString())}).then($s)}const hd={}.STAGING?{projectId:"dev-storytodesign",storageBucket:"dev-storytodesign.appspot.com",apiKey:"AIzaSyBWv_ip8ye3EEmzT1cZ1sqhV1rSoA9LS48",authDomain:"dev-storytodesign.firebaseapp.com",messagingSenderId:"568452031036"}:{apiKey:"AIzaSyAYhTW2QNjar9gR2wslR0V6Yugfh7xia8c",authDomain:"prd-storytodesign.firebaseapp.com",messagingSenderId:"394848236605",projectId:"prd-storytodesign",storageBucket:"prd-storytodesign.appspot.com"},kr={api:"https://h.to.design",concurrent:"https://hc.to.design"},dd={[kr.api]:"https://h2d.divriots.com",[kr.concurrent]:"https://h2d.divriots.com"},js=!{}.STAGING,gh=!js&&{}.STAGING,si=js?kr:gh?{api:"https://us-central1-dev-storytodesign.cloudfunctions.net/api2",concurrent:"https://us-central1-dev-storytodesign.cloudfunctions.net/apiConcurrent"}:{api:`http://${ii()}:5101/dev-storytodesign/us-central1/api2`,concurrent:`http://${ii()}:5101/dev-storytodesign/us-central1/apiConcurrent`},fd=t=>(t==="/html"||t==="/page"||t==="/make"?si.api:si.concurrent)+t,Ws=[{title:"Copy to LLM",description:"Self-contained, curated HTML for LLM consumption."},{title:`Screenshot
Full Page`,description:"Full-page screenshot.",entry:"capture-screen",iconSvg:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="9" width="10" height="7" rx="1.5"/><circle cx="12" cy="12.5" r="2"/></svg>'},{title:`Extract
Design System`,description:"DESIGN.md, SKILL.md and design tokens for your coding agent.",entry:"capture-screen",iconSvg:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" xmlns="http://www.w3.org/2000/svg"><path d="M6 3h9l4 4v14H6z"/><path d="M9 12h6M9 16h6"/><rect x="9" y="7" width="3" height="2" fill="currentColor" stroke="none"/></svg>'}],pd=Ws.filter(t=>t.entry!=="capture-screen"),gd=Ws.filter(t=>t.entry==="capture-screen"),zt=typeof navigator<"u"&&/Mac|iPhone|iPod|iPad/i.test(navigator.userAgent),mh=(t,e)=>{const n=t+7;return`<path d="M${n} ${e}h-${7} a${7} ${7} 0 0 0 0 ${2*7} h${7} z" fill="#F24E1E"/>
    <path d="M${n} ${e}h${7} a${7} ${7} 0 0 1 0 ${2*7} h-${7} z" fill="#FF7262"/>
    <path d="M${n} ${e+2*7}h-${7} a${7} ${7} 0 0 0 0 ${2*7} h${7} z" fill="#A259FF"/>
    <circle cx="${n+7}" cy="${e+3*7}" r="${7}" fill="#1ABCFE"/>
    <path d="M${n} ${e+4*7}h-${7} a${7} ${7} 0 0 0 0 ${2*7} a${7} ${7} 0 0 0 ${7} -${7} z" fill="#0ACF83"/>`},_h=`<svg width="140" height="96" viewBox="0 0 140 96" fill="none" xmlns="http://www.w3.org/2000/svg">
  <rect x="34" y="6" width="72" height="84" rx="8" stroke="currentColor" stroke-width="2"/>
  <path d="M34 22h72" stroke="currentColor" stroke-width="2"/>
  <circle cx="44" cy="14" r="2" fill="currentColor"/>
  <circle cx="52" cy="14" r="2" fill="currentColor"/>
  ${mh(63,36)}
</svg>`,wh=`<svg width="88" height="88" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
  <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
</svg>`,yh=`<video
  src="https://cdn.divriots.com/h2d/makefile-v1.mp4"
  aria-label="Dragging the .make file into Figma's file browser"
  width="240" height="169" autoplay loop muted playsinline
  style="border-radius:2px;box-shadow:0 4px 4px rgba(0,0,0,.3)"></video>`,oi=(t,e,r)=>`<rect x="${t}" y="28" width="44" height="40" rx="8" stroke="currentColor" stroke-width="2"/>
  <text x="${t+22}" y="48" text-anchor="middle" dominant-baseline="central" font-size="${r}" fill="currentColor">${e}</text>`,vh=`<svg width="140" height="96" viewBox="0 0 140 96" fill="none" xmlns="http://www.w3.org/2000/svg">
  ${oi(8,zt?"\u2318":"Ctrl",zt?20:14)}
  <text x="70" y="48" text-anchor="middle" dominant-baseline="central" font-size="16" fill="currentColor">+</text>
  ${oi(88,"V",16)}
</svg>`,ai=[{action:"figma",title:"Send to Figma plugin",description:'Your capture will be available in the "Extension" tab of the Figma plugin.',buttonLabel:"Send to Figma",toolbarLabel:"Send to Figma",verb:"Send",group:"figma",color:"#13a546",iconSvg:'<svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 22 32"><g><path d="M5.3,32c3,0,5.4-2.4,5.4-5.3v-5.4H5.3c-3,0-5.3,2.4-5.3,5.4S2.3,32,5.3,32z" fill="#FFFFFF"/><path d="M0,16c0-3,2.4-5.3,5.3-5.3h5.4v10.6H5.3C2.3,21.3,0,19,0,16z" fill="#CCCCCC"/><path d="M0,5.3C0,2.3,2.4,0,5.3,0h5.4v10.7H5.3C2.3,10.7,0,8.3,0,5.3z" fill="#BBBBBB"/><path d="M10.7,0H16c3,0,5.4,2.4,5.4,5.3S19,10.7,16,10.7h-5.3V0z" fill="#EEEEEE"/><path d="M21.3,16c0,2.9-2.4,5.3-5.3,5.3c-2.9,0-5.3-2.4-5.3-5.3c0,0,0,0,0,0c0-2.9,2.4-5.3,5.3-5.3 C18.9,10.7,21.3,13.1,21.3,16z" fill="#FFFFFF"/></g></svg>',requiresLogin:!0,auto:{available:!0},pendingMessage:"Sending to Figma plugin\u2026",successMessage:"Capture sent to Figma plugin",successDetail:'Go to Figma and launch the html.to.design plugin \u2014 your capture is in the "Extension" tab.',illustration:_h,linksToPlugin:!0},{action:"clipboard",title:"Copy to clipboard",description:`Paste (${zt?"\u2318":"Ctrl"}+V) to your Figma canvas.`,buttonLabel:"Copy",toolbarLabel:"Copy to clipboard",verb:"Copy",group:"figma",color:"#0d99ff",iconSvg:'<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>',requiresLogin:!0,auto:{available:!1},pendingMessage:"Copying to clipboard\u2026",successMessage:"Capture copied to clipboard",successDetail:`You can now paste (${zt?"\u2318":"Ctrl"}+V) into your Figma canvas.`,illustration:vh},{action:"h2d",title:"Download .h2d file",description:"Drag-and-drop the .h2d file into the Figma plugin for maximum privacy.",buttonLabel:"Download .h2d",toolbarLabel:"Download .h2d",verb:"Download",group:"figma",color:"#1e3a8a",iconSvg:'<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>',requiresLogin:!1,auto:{available:!0},pendingMessage:"Preparing download\u2026",successMessage:"Download started",successDetail:"Drag-and-drop the .h2d file into the html.to.design plugin in Figma.",illustration:wh,linksToPlugin:!0},{action:"make",title:"Download Figma Make file",description:"Drag-and-drop the .make file into Figma document explorer.",buttonLabel:"Download .make",toolbarLabel:"Download .make",verb:"Download",group:"ai",color:"#000000",iconSvg:'<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>',requiresLogin:!0,auto:{available:!0},pendingMessage:"Preparing Figma Make project\u2026",successMessage:"Download started",successDetail:"Drag-and-drop the .make file into the Figma document explorer.",illustration:yh,illustrationAfterText:!0}],bh="clipboard",md=t=>t.charAt(0).toLowerCase()+t.slice(1);function _d(t){return ai.find(e=>e.action===t)??ai.find(e=>e.action===bh)}const Ah=[{host:"www\\.figma\\.com",pathname:"^/make/",source:"figma",selector:'iframe[src*="figmaiframepreview.figma.site"],iframe[title="Preview"]',kind:"Preview"},{host:"v0\\.(dev|app)",pathname:"(^|/)(chat|community|templates)/",source:"v0",selector:'iframe[src*=".vusercontent.net"]',kind:"Preview",disableVariations:!0},{host:"bolt\\.new",pathname:"^/~/",source:"bolt",selector:'iframe[src*="webcontainer-api.io"],iframe[title="Bolt preview"]',kind:"Preview"},{host:"claude\\.ai|claude\\.site",pathname:"^/(chat|public\\/artifacts|artifacts\\/latest|artifact|code\\/(artifact|frame))/",source:"claude",selector:'iframe[src*="claudeusercontent.com"],iframe[src*="/code/artifact/"],iframe[title="Rendered HTML content"],iframe[title="Claude content"]',kind:"Artifact"},{host:"claude\\.ai",pathname:"^/design/p/",source:"claude",selector:'iframe[data-testid="html-viewer-iframe"][src*="claudeusercontent.com"]',kind:"Preview"},{host:"gemini\\.google\\.com",pathname:"(/app|^/share)/",source:"gemini",selector:'iframe[src*="scf.usercontent.goog/gemini-code-immersive"]',kind:"Preview"},{host:"aistudio\\.google\\.com",pathname:"^(?:/u/\\d+)?/apps/",source:"aistudio",selector:'iframe[title*="Preview"]',kind:"Preview"},{host:"^mail\\.google\\.com$",pathname:"^/mail/",source:"gmail",selector:".adn.ads .ii.gt > .a3s",kind:"Email",disableVariations:!0,designPage:!0},{host:"replit\\.com",pathname:"^/@",source:"replit",selector:'iframe[src*=".replit.dev"],iframe[title="webview"]',kind:"Preview"},{host:"chatgpt\\.com",pathname:"^/(c|canvas)/",source:"chatgpt",selector:'iframe[src*="web-sandbox.oaiusercontent.com"]:not([title="relay"])',kind:"Canvas"},{host:"www\\.genspark\\.ai",pathname:"^/slides",source:"genspark",selector:'iframe[src*="/api/slides_git/"],iframe[src*="genspark.site/slide_agent"],iframe[src*="genspark.ai/api/files"]',kind:"Slide",disableVariations:!0,designPage:!0,paginate:{stacked:".slide-content",maxPages:200}},{host:"www\\.genspark\\.ai",pathname:"^/(agents|slides|autopilotagent_viewer)",source:"genspark",selector:'iframe[src*="gensparkspace.com"],iframe[src*="genspark.site/slide_agent"],iframe[src*="genspark.ai/api/files"],iframe[src*="/api/code_sandbox_light/preview"]',kind:"Preview"},{host:"\\.gensparkspace\\.com",source:"genspark",selector:'iframe[src*="genspark.site/slide_agent"],iframe[src*="genspark.ai/api/files"]',kind:"Slide"},{host:"app\\.polymet\\.ai",pathname:"^/canvas/",source:"polymet",selector:"iframe:not([title])[srcdoc]",kind:"Canvas"},{host:"skywork\\.ai",pathname:"^/(project|web|share)/",source:"skywork",selector:".website-wrapper iframe, .PPTPreview-box .ppt-page, .pptV2-detail-page-main-list-item .ppt-page",kind:"Canvas"},{host:"lovable\\.dev",pathname:"^/projects/",source:"Lovable.dev",selector:'iframe[src*=".lovableproject.com/"], iframe[src*=".lovable.app/"]',kind:"Preview",click:'.bg-affirmative-primary[aria-label="Code viewer"][data-state="closed"], [data-testid="mobile-view-preview-button"]'},{host:"www\\.magicpath\\.ai",source:"Magicpath",selector:'iframe[src*="api.magicpath.ai/components/"]',kind:"Component"},{host:"manus\\.im",pathname:"^/(app|share/file)/",source:"Manus",selector:"#manus-computer-detail iframe",kind:"Preview"},{host:"www\\.(create\\.xyz|createanything\\.com)",pathname:"^/(build|app)/",source:"Create.xyz",selector:'iframe[src*=".createdusercontent.com"], iframe[src*=".createdevserver.com/"]',kind:"Preview"},{host:"gamma\\.app",pathname:"^/(docs)/",source:"Gamma.app",selector:'[data-node-view-content-inner="document"] .card-body',kind:"Preview"},{host:"uxpilot\\.ai",pathname:"^/(a|s)/",source:"UXPilot",selector:"iframe[srcdoc]",kind:"Preview"},{host:"webflow\\.com",pathname:"^/templates/",source:"webflow",selector:'iframe[src*=".webflow.io"],iframe.mp-iframe',kind:"Template"},{host:"preview\\.themeforest\\.net",source:"themeforest",selector:'iframe[name="preview-frame"]',kind:"Template"},{host:"^.*\\.wix\\.com$",pathname:"^/website-template/",source:"wix",selector:'iframe[src*="www.wix.com"]',kind:"Template"},{host:"themes\\.shopify\\.com",pathname:"^/themes/",source:"Shopify",selector:'iframe[src*=".myshopify.com"][title="Theme preview demo"]',click:'div[data-action="click->demo-store#scrollAndActivate"]',kind:"Template"},{host:"readdy\\.ai",pathname:"^/project/",source:"Readdy",selector:'iframe#preview-iframe,iframe[src*="readdy.ai/viewer/"]',kind:"Preview"},{host:"readdy\\.cc",pathname:"^/preview/",source:"Readdy",exclude:"#watermark",kind:"Preview"},{host:"notion\\.so",source:"Notion",selector:"main#main.notion-frame",kind:"Preview"},{host:"variant\\.(ai|com)",source:"Variant",pathname:"^/(chat/|community|saved)",selector:"iframe.presentation-iframe",kind:"Preview"},{host:"app\\.emergent\\.sh",source:"Emergent",pathname:"^/chat",selector:'iframe[title="URL Preview"]',kind:"Preview"},{host:"meku\\.dev",source:"Meku.dev",pathname:"^/account/projects/",selector:'iframe[src*=".meku.app"]',kind:"Preview"},{host:"www\\.anything\\.com",source:"Anything.com",pathname:"^/build/",selector:'iframe[id="sandbox-iframe"]',kind:"Preview"},{host:"www\\.kimi\\.com",source:"Kimi",pathname:"^/chat/",selector:"iframe.codeRunner, iframe.ppt-frame",kind:"Preview"},{host:"www\\.magicpatterns\\.com",source:"MagicPatterns",pathname:"^/c/",selector:'iframe[src*=".magicpatterns.app"]',kind:"Preview"},{host:"chat\\.deepseek\\.com",pathname:"^/(share|a/chat)/",source:"deepseek",selector:'iframe[src*="deepseeksvc.com/usercontent/"]',kind:"Preview"},{host:"chat\\.qwen\\.ai",pathname:"^/(community|c)/",source:"qwen",selector:'iframe.artifact-iframe-render[src*="qwenlm.io"]',kind:"Preview"},{host:"^www\\.canva\\.com$",pathname:"^/design/(play/?$|[A-Za-z0-9_-]+/[A-Za-z0-9_-]+/(view|edit))",source:"canva",selector:'[data-page-id] > :first-child, main [aria-hidden="false"] div[lang]:not([translate]), [contenteditable][lang], [data-surface="canvas"]:not(:has([data-page-id])):not(:has([contenteditable][lang])) > main',kind:"Design",disableVariations:!0,designPage:!0,paginate:{stacked:"[data-page-id]",pages:'div[role="button"][data-role="timeline-scene"]',slider:'[role="slider"]',next:'[aria-keyshortcuts~="Alt+ArrowRight"]',prev:'[aria-keyshortcuts~="Alt+ArrowLeft"]',maxPages:200}},{host:"^www\\.canva\\.com$",pathname:"^/ai/code/",source:"canva",selector:'iframe[src*=".canvacode.com/codelet/"]',kind:"Preview"},{host:"^docs\\.google\\.com$",pathname:"^/presentation/d/(e/)?[A-Za-z0-9_-]+/(pub|embed)$",source:"google-slides",selector:".punch-viewer-svgpage-svgcontainer",kind:"Slide",disableVariations:!0,designPage:!0,paginate:{slider:".punch-viewer-svgpage-a11yelement",next:".punch-viewer-navbar-next",prev:".punch-viewer-navbar-prev",maxPages:200}},{host:"^docs\\.google\\.com$",pathname:"^/presentation/d/[A-Za-z0-9_-]+/edit$",source:"google-slides",selector:'#pages > svg > g > g[id^="editor-"]',kind:"Slide",disableVariations:!0,designPage:!0,paginate:{pages:"g.punch-filmstrip-thumbnail",maxPages:200}},{host:"stitch\\.withgoogle\\.com",pathname:"^(?:/u/\\d+)?/(preview|projects)/",source:"stitch",selector:'iframe[src*=".appspot.com/"],iframe[title="Stitch - Design with AI"]',kind:"Preview"},{host:"^app-companion-\\d+\\.appspot\\.com$",pathname:"^/(preview|projects)/",source:"stitch",selector:'iframe[srcdoc][sandbox*="allow-scripts"]',kind:"Preview"},{host:"\\.webcontainer-api\\.io$",source:"bolt",selector:".ed-frame-inner > .slide-view[data-deck-slide]",kind:"Slide",disableVariations:!0,designPage:!0,paginate:{counter:".noir-counter",next:".noir-counter + button",prev:"button:has(+ .noir-counter)",maxPages:200}}],Ih="https://html.to.design/assets/previews.config.json";let ci;const li=async t=>{ci??=await fetch(Ih).then(e=>e.json()).catch(()=>Ah);for(const{host:e,pathname:r,...n}of ci)if(!!new RegExp(e).test(t.host)&&!(r&&!new RegExp(r).test(t.pathname)))return n};function Eh(t){const e=[],r=i=>{e.push(...i.querySelectorAll(t));for(const a of i.querySelectorAll("*"))a.shadowRoot&&r(a.shadowRoot)};r(document);const n=e.filter(i=>i.tagName==="IFRAME").map(i=>i.src).filter(Boolean);return{matched:e.length>0,frames:n}}async function zs(t,e){return chrome.scripting.executeScript({target:t,func:Eh,args:[e]}).then(r=>r[0]?.result).catch(()=>{})}async function Th(t,e,r){if(!r.selector)return!1;const n=new URL(e).origin,a=(await chrome.webNavigation.getAllFrames({tabId:t.tabId}).catch(()=>null)??[]).filter(u=>u.url.startsWith(n)).map(u=>u.frameId);if(!a.length)return!0;const c=await zs({tabId:t.tabId,frameIds:a},r.selector);return c?c.matched:!0}async function wd(t,e){const r=await li(new URL(e));if(!r?.selector)return;const n=await zs(t,r.selector);if(!!n?.matched){if(r.paginate)return r;for(const i of n.frames){const a=await li(new URL(i)).catch(()=>{});if(a?.paginate&&await Th(t,i,a))return{...r,kind:a.kind,paginate:a.paginate,disableVariations:a.disableVariations??r.disableVariations}}return r}}async function Sh(t,e){const{promise:r,resolve:n}=Co(),i=(a,c)=>{a.byExtensionName==="html.to.design"&&(c({filename:e}),n())};chrome.downloads.onDeterminingFilename.addListener(i);try{await chrome.downloads.download({url:t,filename:e,saveAs:!1}),await Promise.race([Ro(3e3,{errorOnTimeout:"timed out"}),r]).catch(()=>console.error("Download listener was not called within allocated timeout"))}finally{chrome.downloads.onDeterminingFilename.removeListener(i)}}async function yd(t,e){const r=await caches.open("h2d-downloads"),n=await r.match(t);if(!n)throw new Error("Download data not found in cache");const i=await n.blob(),a=URL.createObjectURL(i);try{await Sh(a,e)}finally{setTimeout(()=>URL.revokeObjectURL(a),5e3)}await r.delete(t)}const kh=.5;async function vd(t){const e=await chrome.scripting.executeScript({target:{tabId:t},args:[kh],func:async r=>{const n=a=>a.startsWith("blob:")?fetch(a).then(c=>(c.body?.cancel(),c.ok),()=>!1):Promise.resolve(!0);if(document.contentType==="application/pdf")return{url:location.href,title:document.title,readable:await n(location.href)};const i=innerWidth*innerHeight;for(const a of document.querySelectorAll("iframe")){const c=a.contentDocument;if(c?.contentType!=="application/pdf")continue;const u=a.getBoundingClientRect(),d=Math.min(u.right,innerWidth)-Math.max(u.left,0),f=Math.min(u.bottom,innerHeight)-Math.max(u.top,0);if(d<=0||f<=0||d*f/i<r)continue;const w=c.URL.split("#")[0];if(await n(w))return{url:w,title:document.title,readable:!0}}}}).then(r=>r[0]?.result).catch(()=>{});return e&&{url:e.url,name:xh(e.url,e.title),unreadable:Gs(e.url)?e.readable?void 0:Ks:qs}}const Gs=t=>/^(https?|blob):/i.test(t),qs="Local PDFs can\u2019t be read from the browser. Drop the file into the pdf.to.design plugin in Figma instead.",Ks="This PDF can\u2019t be read anymore. Reload the page, or download the PDF and drop it into the pdf.to.design plugin in Figma.",ui=8*1024*1024;async function bd(t,e){if(!Gs(e))throw new Error(qs);const r=`__h2dPdf_${crypto.randomUUID()}`,[{result:n}]=await chrome.scripting.executeScript({target:{tabId:t},args:[r,e,Ks],func:async(a,c,u)=>{if(!(location.href===c||[...document.querySelectorAll("iframe")].some(w=>w.contentDocument?.URL.split("#")[0]===c)))return"The tab shows another page";const f=await fetch(c,{cache:"force-cache"}).catch(()=>null);return f?f.ok?(globalThis[a]=new Uint8Array(await f.arrayBuffer()),globalThis[a].byteLength):`Could not read the PDF (${f.status})`:u}});if(typeof n!="number")throw new Error(n||"Could not read the PDF");const i=[];try{for(let a=0;a<n;a+=ui){const[{result:c}]=await chrome.scripting.executeScript({target:{tabId:t},args:[r,a,ui],func:(u,d,f)=>new Promise(w=>{const g=new FileReader;g.onload=()=>w(g.result),g.readAsDataURL(new Blob([globalThis[u].subarray(d,d+f)]))})});i.push(await(await fetch(c)).arrayBuffer())}}finally{await chrome.scripting.executeScript({target:{tabId:t},args:[r],func:a=>void delete globalThis[a]}).catch(()=>{})}return new Uint8Array(await new Blob(i).arrayBuffer())}function xh(t,e=""){if(/^blob:/i.test(t))return e.slice(0,200).match(/[^/\\:*?"<>|]*?[^\s/\\:*?"<>|]\.pdf\b/i)?.[0].trim()??"document.pdf";try{const r=decodeURIComponent(new URL(t).pathname.split("/").pop());if(r)return/\.pdf$/i.test(r)?r:`${r}.pdf`}catch{}return"document.pdf"}export{ai as $,Ei as A,Io as B,ld as C,Eo as D,Ph as E,Oh as F,To as G,yo as H,Ee as I,ad as J,Vs as K,hh as L,Jt as M,Oe as N,od as O,zh as P,la as Q,ud as R,ph as S,Kt as T,Lh as U,Ji as V,Qe as W,fd as X,_d as Y,md as Z,_o as _,Wh as a,pd as a0,Xu as a1,Ul as a2,id as a3,Ju as a4,$h as a5,jo as a6,vd as a7,rd as a8,td as a9,xa as aA,At as aB,le as aC,vt as aD,Ua as aE,os as aF,Ye as aG,Ur as aH,$a as aI,ns as aJ,V as aK,ee as aL,Se as aM,es as aN,bt as aO,ka as aP,Ea as aQ,$c as aR,hd as aS,dd as aT,Ui as aU,bd as aV,cd as aW,Sh as aX,Uh as aa,wd as ab,Bh as ac,ed as ad,Ro as ae,gd as af,Fh as ag,Jr as ah,nd as ai,yd as aj,Rh as ak,Sn as al,Ni as am,Wo as an,jh as ao,qh as ap,Oi as aq,Pi as ar,Ri as as,No as at,Sa as au,Ca as av,Le as aw,mt as ax,Xe as ay,je as az,Gh as b,Hh as c,Jh as d,Vh as e,Yh as f,qt as g,Lo as h,Mh as i,Bo as j,ut as k,j as l,Dh as m,Ch as n,Xh as o,Uo as p,Fo as q,Li as r,Zh as s,Qh as t,tt as u,Co as v,Fc as w,Nh as x,Ii as y,sd as z};
