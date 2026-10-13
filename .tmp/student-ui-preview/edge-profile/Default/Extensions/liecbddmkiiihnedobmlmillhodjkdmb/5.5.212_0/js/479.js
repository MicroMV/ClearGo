"use strict";
!function(){try{var e="undefined"!=typeof window?window:"undefined"!=typeof global?global:"undefined"!=typeof globalThis?globalThis:"undefined"!=typeof self?self:{},n=(new e.Error).stack;n&&(e._sentryDebugIds=e._sentryDebugIds||{},e._sentryDebugIds[n]="fb2e1eb8-f989-5b2f-b95a-787f71249390")}catch(e){}}();
(self.rspackChunk_loomhq_chrome_extension=self.rspackChunk_loomhq_chrome_extension||[]).push([[479],{71034(e,i,o){let c=["ZoomAudioDevice","LoomAudioDevice (Virtual)","Microsoft Teams Audio Device (Virtual)"];o.d(i,{},{getDevices:()=>new Promise(e=>{navigator.mediaDevices.enumerateDevices().then(i=>{let o=[],s=[];i.forEach(e=>{if(e.label&&e.deviceId&&!c.some(i=>e.label.includes(i))){let i={label:e.label,deviceId:e.deviceId,id:e.deviceId};"videoinput"===e.kind?s.push(i):"audioinput"===e.kind&&o.push(i)}}),e({mics:o,cameras:s})}).catch(()=>{e({mics:[],cameras:[]})})})})}}]);
//# sourceMappingURL=479.js.map
//# debugId=fb2e1eb8-f989-5b2f-b95a-787f71249390
