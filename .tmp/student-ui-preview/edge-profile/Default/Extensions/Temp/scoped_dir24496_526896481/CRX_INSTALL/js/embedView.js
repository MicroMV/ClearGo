
!function(){try{var e="undefined"!=typeof window?window:"undefined"!=typeof global?global:"undefined"!=typeof globalThis?globalThis:"undefined"!=typeof self?self:{},n=(new e.Error).stack;n&&(e._sentryDebugIds=e._sentryDebugIds||{},e._sentryDebugIds[n]="dabfd11b-6183-5e36-9bb6-c4048c689e76")}catch(e){}}();
(()=>{"use strict";var e,n;let t,r,o="WEBEXT_REDUX_STORAGE_V1.1",i={devboxName:"",staffId:""},c=!1,u=null;function a(){return"u">typeof chrome&&!!chrome.storage?.local}function l(e){return e?.trim()??""}function d(e){return{devboxName:l(e?.devboxName),staffId:l(e?.staffId)}}function m(e){return d(e?.devEnv)}async function f(){return a()?i=d(m((await chrome.storage.local.get(o))[o])):i}function s(e){return{[Symbol.toPrimitive]:()=>e(),toString:()=>e(),valueOf:()=>e()}}function h(){return"https://www.loom.com"}a()&&!c&&(chrome.storage.onChanged.addListener((e,n)=>{if("local"!==n)return;let t=e[o];t&&(i=d(m(t.newValue)))}),c=!0),u||(u=f().catch(()=>i).finally(()=>{u=null})),s(function(){return"www.loom.com"}),s(function(){return"loom.com"}),s(h),["test","development"].includes("production"),s(function(){return`${h()}/api/util/ping`}),s(function(){return`${h()}/login`}),s(function(){return`${h()}/looms`}),s(function(){return`${h()}/settings/workspace#members`}),s(function(){return`${h()}/settings/workspace#plans`}),s(function(){return`${h()}/settings/billing`}),s(function(){return`${h()}/notifications/all`}),s(function(){return`${h()}/my-videos`}),s(function(){return`${h()}/canvas`}),chrome.runtime.id,s(function(){return`${h()}/generate-video`});let p=document.createElement("style");p.append(`
  html,
  body {
    overflow: hidden;
  
    margin: 0;
    width: 100%;
    height: 100%;
    padding: 0;
  
    background: transparent;
  }
  
  iframe {
    border: none;
    width: 100%;
    height: 100%;
  }
  `),document.head.append(p);let g=document.createElement("iframe"),w=(e="session_id",n=window.location.href,e=e.replace(/[[]/,"\\[").replace(/[\]]/,"\\]"),(t=document.createElement("a")).href=n,null===(r=RegExp("[\\?&]"+e+"=([^&#]*)").exec(t.search))?"":r[1].replace(/\+/g," "));g.allowFullscreen=!0,g.frameBorder="0",g.src=`${h()}/embed/${w}?from_link_expand=true&${window.location.search}`,document.body.appendChild(g)})();
//# sourceMappingURL=embedView.js.map
//# debugId=dabfd11b-6183-5e36-9bb6-c4048c689e76
