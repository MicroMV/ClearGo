import{m as Ze,n as qe,o as et,q as W,_ as pe,C as me,v as ge,w as tt,S as ue,E as le,x as rt,y as Ee,z as Ce,A,B as nt,F as Ne,D as it,G as Pe,H as m,I as O,J as M,K as L,M as C,N as F,O as T,P as Ue,Q as Le,R as ie,T as Oe,U as Re,l as I,c as g,V as st,W as at,X as ot,Y as ct,Z as dt,$ as _e,a0 as ut}from"./index-0a1d6aaf.js";function De(){return{"dependent-sdk-initialized-before-auth":"Another Firebase SDK was initialized and is trying to use Auth before Auth is initialized. Please be sure to call `initializeAuth` or `getAuth` before starting any other Firebase SDK."}}const lt=De,Me=new le("auth","Firebase",De());/**
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
 */const ee=new nt("@firebase/auth");function ht(r,...e){ee.logLevel<=Ee.WARN&&ee.warn(`Auth (${ue}): ${r}`,...e)}function Z(r,...e){ee.logLevel<=Ee.ERROR&&ee.error(`Auth (${ue}): ${r}`,...e)}/**
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
 */function ce(r,...e){throw he(r,...e)}function Fe(r,...e){return he(r,...e)}function ft(r,e,t){const n=Object.assign(Object.assign({},lt()),{[e]:t});return new le("auth","Firebase",n).create(e,{appName:r.name})}function he(r,...e){if(typeof r!="string"){const t=e[0],n=[...e.slice(1)];return n[0]&&(n[0].appName=r.name),r._errorFactory.create(t,...n)}return Me.create(r,...e)}function f(r,e,...t){if(!r)throw he(e,...t)}function H(r){const e="INTERNAL ASSERTION FAILED: "+r;throw Z(e),new Error(e)}function te(r,e){r||H(e)}function pt(){return ye()==="http:"||ye()==="https:"}function ye(){var r;return typeof self<"u"&&((r=self.location)===null||r===void 0?void 0:r.protocol)||null}/**
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
 */function mt(){return typeof navigator<"u"&&navigator&&"onLine"in navigator&&typeof navigator.onLine=="boolean"&&(pt()||tt()||"connection"in navigator)?navigator.onLine:!0}function gt(){if(typeof navigator>"u")return null;const r=navigator;return r.languages&&r.languages[0]||r.language||null}/**
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
 */class j{constructor(e,t){this.shortDelay=e,this.longDelay=t,te(t>e,"Short delay should be less than long delay!"),this.isMobile=qe()||et()}get(){return mt()?this.isMobile?this.longDelay:this.shortDelay:Math.min(5e3,this.shortDelay)}}/**
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
 */function _t(r,e){te(r.emulator,"Emulator should always be set here");const{url:t}=r.emulator;return e?`${t}${e.startsWith("/")?e.slice(1):e}`:t}/**
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
 */class xe{static initialize(e,t,n){this.fetchImpl=e,t&&(this.headersImpl=t),n&&(this.responseImpl=n)}static fetch(){if(this.fetchImpl)return this.fetchImpl;if(typeof self<"u"&&"fetch"in self)return self.fetch;if(typeof globalThis<"u"&&globalThis.fetch)return globalThis.fetch;if(typeof fetch<"u")return fetch;H("Could not find fetch implementation, make sure you call FetchProvider.initialize() with an appropriate polyfill")}static headers(){if(this.headersImpl)return this.headersImpl;if(typeof self<"u"&&"Headers"in self)return self.Headers;if(typeof globalThis<"u"&&globalThis.Headers)return globalThis.Headers;if(typeof Headers<"u")return Headers;H("Could not find Headers implementation, make sure you call FetchProvider.initialize() with an appropriate polyfill")}static response(){if(this.responseImpl)return this.responseImpl;if(typeof self<"u"&&"Response"in self)return self.Response;if(typeof globalThis<"u"&&globalThis.Response)return globalThis.Response;if(typeof Response<"u")return Response;H("Could not find Response implementation, make sure you call FetchProvider.initialize() with an appropriate polyfill")}}/**
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
 */const yt={CREDENTIAL_MISMATCH:"custom-token-mismatch",MISSING_CUSTOM_TOKEN:"internal-error",INVALID_IDENTIFIER:"invalid-email",MISSING_CONTINUE_URI:"internal-error",INVALID_PASSWORD:"wrong-password",MISSING_PASSWORD:"missing-password",INVALID_LOGIN_CREDENTIALS:"invalid-credential",EMAIL_EXISTS:"email-already-in-use",PASSWORD_LOGIN_DISABLED:"operation-not-allowed",INVALID_IDP_RESPONSE:"invalid-credential",INVALID_PENDING_TOKEN:"invalid-credential",FEDERATED_USER_ID_ALREADY_LINKED:"credential-already-in-use",MISSING_REQ_TYPE:"internal-error",EMAIL_NOT_FOUND:"user-not-found",RESET_PASSWORD_EXCEED_LIMIT:"too-many-requests",EXPIRED_OOB_CODE:"expired-action-code",INVALID_OOB_CODE:"invalid-action-code",MISSING_OOB_CODE:"internal-error",CREDENTIAL_TOO_OLD_LOGIN_AGAIN:"requires-recent-login",INVALID_ID_TOKEN:"invalid-user-token",TOKEN_EXPIRED:"user-token-expired",USER_NOT_FOUND:"user-token-expired",TOO_MANY_ATTEMPTS_TRY_LATER:"too-many-requests",PASSWORD_DOES_NOT_MEET_REQUIREMENTS:"password-does-not-meet-requirements",INVALID_CODE:"invalid-verification-code",INVALID_SESSION_INFO:"invalid-verification-id",INVALID_TEMPORARY_PROOF:"invalid-credential",MISSING_SESSION_INFO:"missing-verification-id",SESSION_EXPIRED:"code-expired",MISSING_ANDROID_PACKAGE_NAME:"missing-android-pkg-name",UNAUTHORIZED_DOMAIN:"unauthorized-continue-uri",INVALID_OAUTH_CLIENT_ID:"invalid-oauth-client-id",ADMIN_ONLY_OPERATION:"admin-restricted-operation",INVALID_MFA_PENDING_CREDENTIAL:"invalid-multi-factor-session",MFA_ENROLLMENT_NOT_FOUND:"multi-factor-info-not-found",MISSING_MFA_ENROLLMENT_ID:"missing-multi-factor-info",MISSING_MFA_PENDING_CREDENTIAL:"missing-multi-factor-session",SECOND_FACTOR_EXISTS:"second-factor-already-in-use",SECOND_FACTOR_LIMIT_EXCEEDED:"maximum-second-factor-count-exceeded",BLOCKING_FUNCTION_ERROR_RESPONSE:"internal-error",RECAPTCHA_NOT_ENABLED:"recaptcha-not-enabled",MISSING_RECAPTCHA_TOKEN:"missing-recaptcha-token",INVALID_RECAPTCHA_TOKEN:"invalid-recaptcha-token",INVALID_RECAPTCHA_ACTION:"invalid-recaptcha-action",MISSING_CLIENT_TYPE:"missing-client-type",MISSING_RECAPTCHA_VERSION:"missing-recaptcha-version",INVALID_RECAPTCHA_VERSION:"invalid-recaptcha-version",INVALID_REQ_TYPE:"invalid-req-type"};/**
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
 */const It=new j(3e4,6e4);function fe(r,e){return r.tenantId&&!e.tenantId?Object.assign(Object.assign({},e),{tenantId:r.tenantId}):e}async function J(r,e,t,n,i={}){return Ve(r,i,async()=>{let s={},a={};n&&(e==="GET"?a=n:s={body:JSON.stringify(n)});const c=Pe(Object.assign({key:r.config.apiKey},a)).slice(1),o=await r._getAdditionalHeaders();return o["Content-Type"]="application/json",r.languageCode&&(o["X-Firebase-Locale"]=r.languageCode),xe.fetch()(Be(r,r.config.apiHost,t,c),Object.assign({method:e,headers:o,referrerPolicy:"no-referrer"},s))})}async function Ve(r,e,t){r._canInitEmulator=!1;const n=Object.assign(Object.assign({},yt),e);try{const i=new wt(r),s=await Promise.race([t(),i.promise]);i.clearNetworkTimeout();const a=await s.json();if("needConfirmation"in a)throw X(r,"account-exists-with-different-credential",a);if(s.ok&&!("errorMessage"in a))return a;{const c=s.ok?a.errorMessage:a.error.message,[o,h]=c.split(" : ");if(o==="FEDERATED_USER_ID_ALREADY_LINKED")throw X(r,"credential-already-in-use",a);if(o==="EMAIL_EXISTS")throw X(r,"email-already-in-use",a);if(o==="USER_DISABLED")throw X(r,"user-disabled",a);const v=n[o]||o.toLowerCase().replace(/[_\s]+/g,"-");if(h)throw ft(r,v,h);ce(r,v)}}catch(i){if(i instanceof Ne)throw i;ce(r,"network-request-failed",{message:String(i)})}}async function vt(r,e,t,n,i={}){const s=await J(r,e,t,n,i);return"mfaPendingCredential"in s&&ce(r,"multi-factor-auth-required",{_serverResponse:s}),s}function Be(r,e,t,n){const i=`${e}${t}?${n}`;return r.config.emulator?_t(r.config,i):`${r.config.apiScheme}://${i}`}class wt{constructor(e){this.auth=e,this.timer=null,this.promise=new Promise((t,n)=>{this.timer=setTimeout(()=>n(Fe(this.auth,"network-request-failed")),It.get())})}clearNetworkTimeout(){clearTimeout(this.timer)}}function X(r,e,t){const n={appName:r.name};t.email&&(n.email=t.email),t.phoneNumber&&(n.phoneNumber=t.phoneNumber);const i=Fe(r,e,n);return i.customData._tokenResponse=t,i}/**
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
 */async function Tt(r,e){return J(r,"POST","/v1/accounts:delete",e)}async function bt(r,e){return J(r,"POST","/v1/accounts:lookup",e)}/**
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
 */function z(r){if(r)try{const e=new Date(Number(r));if(!isNaN(e.getTime()))return e.toUTCString()}catch{}}async function At(r,e=!1){const t=W(r),n=await t.getIdToken(e),i=Ke(n);f(i&&i.exp&&i.auth_time&&i.iat,t.auth,"internal-error");const s=typeof i.firebase=="object"?i.firebase:void 0,a=s==null?void 0:s.sign_in_provider;return{claims:i,token:n,authTime:z(ae(i.auth_time)),issuedAtTime:z(ae(i.iat)),expirationTime:z(ae(i.exp)),signInProvider:a||null,signInSecondFactor:(s==null?void 0:s.sign_in_second_factor)||null}}function ae(r){return Number(r)*1e3}function Ke(r){const[e,t,n]=r.split(".");if(e===void 0||t===void 0||n===void 0)return Z("JWT malformed, contained fewer than 3 sections"),null;try{const i=it(t);return i?JSON.parse(i):(Z("Failed to decode base64 JWT payload"),null)}catch(i){return Z("Caught error parsing JWT payload as JSON",i==null?void 0:i.toString()),null}}function kt(r){const e=Ke(r);return f(e,"internal-error"),f(typeof e.exp<"u","internal-error"),f(typeof e.iat<"u","internal-error"),Number(e.exp)-Number(e.iat)}/**
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
 */async function de(r,e,t=!1){if(t)return e;try{return await e}catch(n){throw n instanceof Ne&&St(n)&&r.auth.currentUser===r&&await r.auth.signOut(),n}}function St({code:r}){return r==="auth/user-disabled"||r==="auth/user-token-expired"}/**
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
 */class Et{constructor(e){this.user=e,this.isRunning=!1,this.timerId=null,this.errorBackoff=3e4}_start(){this.isRunning||(this.isRunning=!0,this.schedule())}_stop(){this.isRunning&&(this.isRunning=!1,this.timerId!==null&&clearTimeout(this.timerId))}getInterval(e){var t;if(e){const n=this.errorBackoff;return this.errorBackoff=Math.min(this.errorBackoff*2,96e4),n}else{this.errorBackoff=3e4;const i=((t=this.user.stsTokenManager.expirationTime)!==null&&t!==void 0?t:0)-Date.now()-3e5;return Math.max(0,i)}}schedule(e=!1){if(!this.isRunning)return;const t=this.getInterval(e);this.timerId=setTimeout(async()=>{await this.iteration()},t)}async iteration(){try{await this.user.getIdToken(!0)}catch(e){(e==null?void 0:e.code)==="auth/network-request-failed"&&this.schedule(!0);return}this.schedule()}}/**
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
 */class $e{constructor(e,t){this.createdAt=e,this.lastLoginAt=t,this._initializeTime()}_initializeTime(){this.lastSignInTime=z(this.lastLoginAt),this.creationTime=z(this.createdAt)}_copy(e){this.createdAt=e.createdAt,this.lastLoginAt=e.lastLoginAt,this._initializeTime()}toJSON(){return{createdAt:this.createdAt,lastLoginAt:this.lastLoginAt}}}/**
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
 */async function re(r){var e;const t=r.auth,n=await r.getIdToken(),i=await de(r,bt(t,{idToken:n}));f(i==null?void 0:i.users.length,t,"internal-error");const s=i.users[0];r._notifyReloadListener(s);const a=!((e=s.providerUserInfo)===null||e===void 0)&&e.length?Pt(s.providerUserInfo):[],c=Nt(r.providerData,a),o=r.isAnonymous,h=!(r.email&&s.passwordHash)&&!(c!=null&&c.length),v=o?h:!1,b={uid:s.localId,displayName:s.displayName||null,photoURL:s.photoUrl||null,email:s.email||null,emailVerified:s.emailVerified||!1,phoneNumber:s.phoneNumber||null,tenantId:s.tenantId||null,providerData:c,metadata:new $e(s.createdAt,s.lastLoginAt),isAnonymous:v};Object.assign(r,b)}async function Ct(r){const e=W(r);await re(e),await e.auth._persistUserIfCurrent(e),e.auth._notifyListenersIfCurrent(e)}function Nt(r,e){return[...r.filter(n=>!e.some(i=>i.providerId===n.providerId)),...e]}function Pt(r){return r.map(e=>{var{providerId:t}=e,n=Ce(e,["providerId"]);return{providerId:t,uid:n.rawId||"",displayName:n.displayName||null,email:n.email||null,phoneNumber:n.phoneNumber||null,photoURL:n.photoUrl||null}})}/**
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
 */async function Ut(r,e){const t=await Ve(r,{},async()=>{const n=Pe({grant_type:"refresh_token",refresh_token:e}).slice(1),{tokenApiHost:i,apiKey:s}=r.config,a=Be(r,i,"/v1/token",`key=${s}`),c=await r._getAdditionalHeaders();return c["Content-Type"]="application/x-www-form-urlencoded",xe.fetch()(a,{method:"POST",headers:c,body:n})});return{accessToken:t.access_token,expiresIn:t.expires_in,refreshToken:t.refresh_token}}async function Lt(r,e){return J(r,"POST","/v2/accounts:revokeToken",fe(r,e))}/**
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
 */class G{constructor(){this.refreshToken=null,this.accessToken=null,this.expirationTime=null}get isExpired(){return!this.expirationTime||Date.now()>this.expirationTime-3e4}updateFromServerResponse(e){f(e.idToken,"internal-error"),f(typeof e.idToken<"u","internal-error"),f(typeof e.refreshToken<"u","internal-error");const t="expiresIn"in e&&typeof e.expiresIn<"u"?Number(e.expiresIn):kt(e.idToken);this.updateTokensAndExpiration(e.idToken,e.refreshToken,t)}async getToken(e,t=!1){return f(!this.accessToken||this.refreshToken,e,"user-token-expired"),!t&&this.accessToken&&!this.isExpired?this.accessToken:this.refreshToken?(await this.refresh(e,this.refreshToken),this.accessToken):null}clearRefreshToken(){this.refreshToken=null}async refresh(e,t){const{accessToken:n,refreshToken:i,expiresIn:s}=await Ut(e,t);this.updateTokensAndExpiration(n,i,Number(s))}updateTokensAndExpiration(e,t,n){this.refreshToken=t||null,this.accessToken=e||null,this.expirationTime=Date.now()+n*1e3}static fromJSON(e,t){const{refreshToken:n,accessToken:i,expirationTime:s}=t,a=new G;return n&&(f(typeof n=="string","internal-error",{appName:e}),a.refreshToken=n),i&&(f(typeof i=="string","internal-error",{appName:e}),a.accessToken=i),s&&(f(typeof s=="number","internal-error",{appName:e}),a.expirationTime=s),a}toJSON(){return{refreshToken:this.refreshToken,accessToken:this.accessToken,expirationTime:this.expirationTime}}_assign(e){this.accessToken=e.accessToken,this.refreshToken=e.refreshToken,this.expirationTime=e.expirationTime}_clone(){return Object.assign(new G,this.toJSON())}_performRefresh(){return H("not implemented")}}/**
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
 */function S(r,e){f(typeof r=="string"||typeof r>"u","internal-error",{appName:e})}class U{constructor(e){var{uid:t,auth:n,stsTokenManager:i}=e,s=Ce(e,["uid","auth","stsTokenManager"]);this.providerId="firebase",this.proactiveRefresh=new Et(this),this.reloadUserInfo=null,this.reloadListener=null,this.uid=t,this.auth=n,this.stsTokenManager=i,this.accessToken=i.accessToken,this.displayName=s.displayName||null,this.email=s.email||null,this.emailVerified=s.emailVerified||!1,this.phoneNumber=s.phoneNumber||null,this.photoURL=s.photoURL||null,this.isAnonymous=s.isAnonymous||!1,this.tenantId=s.tenantId||null,this.providerData=s.providerData?[...s.providerData]:[],this.metadata=new $e(s.createdAt||void 0,s.lastLoginAt||void 0)}async getIdToken(e){const t=await de(this,this.stsTokenManager.getToken(this.auth,e));return f(t,this.auth,"internal-error"),this.accessToken!==t&&(this.accessToken=t,await this.auth._persistUserIfCurrent(this),this.auth._notifyListenersIfCurrent(this)),t}getIdTokenResult(e){return At(this,e)}reload(){return Ct(this)}_assign(e){this!==e&&(f(this.uid===e.uid,this.auth,"internal-error"),this.displayName=e.displayName,this.photoURL=e.photoURL,this.email=e.email,this.emailVerified=e.emailVerified,this.phoneNumber=e.phoneNumber,this.isAnonymous=e.isAnonymous,this.tenantId=e.tenantId,this.providerData=e.providerData.map(t=>Object.assign({},t)),this.metadata._copy(e.metadata),this.stsTokenManager._assign(e.stsTokenManager))}_clone(e){const t=new U(Object.assign(Object.assign({},this),{auth:e,stsTokenManager:this.stsTokenManager._clone()}));return t.metadata._copy(this.metadata),t}_onReload(e){f(!this.reloadListener,this.auth,"internal-error"),this.reloadListener=e,this.reloadUserInfo&&(this._notifyReloadListener(this.reloadUserInfo),this.reloadUserInfo=null)}_notifyReloadListener(e){this.reloadListener?this.reloadListener(e):this.reloadUserInfo=e}_startProactiveRefresh(){this.proactiveRefresh._start()}_stopProactiveRefresh(){this.proactiveRefresh._stop()}async _updateTokensIfNecessary(e,t=!1){let n=!1;e.idToken&&e.idToken!==this.stsTokenManager.accessToken&&(this.stsTokenManager.updateFromServerResponse(e),n=!0),t&&await re(this),await this.auth._persistUserIfCurrent(this),n&&this.auth._notifyListenersIfCurrent(this)}async delete(){const e=await this.getIdToken();return await de(this,Tt(this.auth,{idToken:e})),this.stsTokenManager.clearRefreshToken(),this.auth.signOut()}toJSON(){return Object.assign(Object.assign({uid:this.uid,email:this.email||void 0,emailVerified:this.emailVerified,displayName:this.displayName||void 0,isAnonymous:this.isAnonymous,photoURL:this.photoURL||void 0,phoneNumber:this.phoneNumber||void 0,tenantId:this.tenantId||void 0,providerData:this.providerData.map(e=>Object.assign({},e)),stsTokenManager:this.stsTokenManager.toJSON(),_redirectEventId:this._redirectEventId},this.metadata.toJSON()),{apiKey:this.auth.config.apiKey,appName:this.auth.name})}get refreshToken(){return this.stsTokenManager.refreshToken||""}static _fromJSON(e,t){var n,i,s,a,c,o,h,v;const b=(n=t.displayName)!==null&&n!==void 0?n:void 0,x=(i=t.email)!==null&&i!==void 0?i:void 0,V=(s=t.phoneNumber)!==null&&s!==void 0?s:void 0,B=(a=t.photoURL)!==null&&a!==void 0?a:void 0,K=(c=t.tenantId)!==null&&c!==void 0?c:void 0,R=(o=t._redirectEventId)!==null&&o!==void 0?o:void 0,d=(h=t.createdAt)!==null&&h!==void 0?h:void 0,u=(v=t.lastLoginAt)!==null&&v!==void 0?v:void 0,{uid:l,emailVerified:p,isAnonymous:Y,providerData:N,stsTokenManager:k}=t;f(l&&k,e,"internal-error");const Xe=G.fromJSON(this.name,k);f(typeof l=="string",e,"internal-error"),S(b,e.name),S(x,e.name),f(typeof p=="boolean",e,"internal-error"),f(typeof Y=="boolean",e,"internal-error"),S(V,e.name),S(B,e.name),S(K,e.name),S(R,e.name),S(d,e.name),S(u,e.name);const se=new U({uid:l,auth:e,email:x,emailVerified:p,displayName:b,isAnonymous:Y,photoURL:B,phoneNumber:V,tenantId:K,stsTokenManager:Xe,createdAt:d,lastLoginAt:u});return N&&Array.isArray(N)&&(se.providerData=N.map(Qe=>Object.assign({},Qe))),R&&(se._redirectEventId=R),se}static async _fromIdTokenResponse(e,t,n=!1){const i=new G;i.updateFromServerResponse(t);const s=new U({uid:t.localId,auth:e,stsTokenManager:i,isAnonymous:n});return await re(s),s}}/**
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
 */const Ie=new Map;function P(r){te(r instanceof Function,"Expected a class definition");let e=Ie.get(r);return e?(te(e instanceof r,"Instance stored in cache mismatched with class"),e):(e=new r,Ie.set(r,e),e)}/**
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
 */class He{constructor(){this.type="NONE",this.storage={}}async _isAvailable(){return!0}async _set(e,t){this.storage[e]=t}async _get(e){const t=this.storage[e];return t===void 0?null:t}async _remove(e){delete this.storage[e]}_addListener(e,t){}_removeListener(e,t){}}He.type="NONE";const ve=He;/**
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
 */function oe(r,e,t){return`firebase:${r}:${e}:${t}`}class D{constructor(e,t,n){this.persistence=e,this.auth=t,this.userKey=n;const{config:i,name:s}=this.auth;this.fullUserKey=oe(this.userKey,i.apiKey,s),this.fullPersistenceKey=oe("persistence",i.apiKey,s),this.boundEventHandler=t._onStorageEvent.bind(t),this.persistence._addListener(this.fullUserKey,this.boundEventHandler)}setCurrentUser(e){return this.persistence._set(this.fullUserKey,e.toJSON())}async getCurrentUser(){const e=await this.persistence._get(this.fullUserKey);return e?U._fromJSON(this.auth,e):null}removeCurrentUser(){return this.persistence._remove(this.fullUserKey)}savePersistenceForRedirect(){return this.persistence._set(this.fullPersistenceKey,this.persistence.type)}async setPersistence(e){if(this.persistence===e)return;const t=await this.getCurrentUser();if(await this.removeCurrentUser(),this.persistence=e,t)return this.setCurrentUser(t)}delete(){this.persistence._removeListener(this.fullUserKey,this.boundEventHandler)}static async create(e,t,n="authUser"){if(!t.length)return new D(P(ve),e,n);const i=(await Promise.all(t.map(async h=>{if(await h._isAvailable())return h}))).filter(h=>h);let s=i[0]||P(ve);const a=oe(n,e.config.apiKey,e.name);let c=null;for(const h of t)try{const v=await h._get(a);if(v){const b=U._fromJSON(e,v);h!==s&&(c=b),s=h;break}}catch{}const o=i.filter(h=>h._shouldAllowMigration);return!s._shouldAllowMigration||!o.length?new D(s,e,n):(s=o[0],c&&await s._set(a,c.toJSON()),await Promise.all(t.map(async h=>{if(h!==s)try{await h._remove(a)}catch{}})),new D(s,e,n))}}/**
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
 */function we(r){const e=r.toLowerCase();if(e.includes("opera/")||e.includes("opr/")||e.includes("opios/"))return"Opera";if(Mt(e))return"IEMobile";if(e.includes("msie")||e.includes("trident/"))return"IE";if(e.includes("edge/"))return"Edge";if(Ot(e))return"Firefox";if(e.includes("silk/"))return"Silk";if(xt(e))return"Blackberry";if(Vt(e))return"Webos";if(Rt(e))return"Safari";if((e.includes("chrome/")||Dt(e))&&!e.includes("edge/"))return"Chrome";if(Ft(e))return"Android";{const t=/([a-zA-Z\d\.]+)\/[a-zA-Z\d\.]*$/,n=r.match(t);if((n==null?void 0:n.length)===2)return n[1]}return"Other"}function Ot(r=A()){return/firefox\//i.test(r)}function Rt(r=A()){const e=r.toLowerCase();return e.includes("safari/")&&!e.includes("chrome/")&&!e.includes("crios/")&&!e.includes("android")}function Dt(r=A()){return/crios\//i.test(r)}function Mt(r=A()){return/iemobile/i.test(r)}function Ft(r=A()){return/android/i.test(r)}function xt(r=A()){return/blackberry/i.test(r)}function Vt(r=A()){return/webos/i.test(r)}/**
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
 */function ze(r,e=[]){let t;switch(r){case"Browser":t=we(A());break;case"Worker":t=`${we(A())}-${r}`;break;default:t=r}const n=e.length?e.join(","):"FirebaseCore-web";return`${t}/JsCore/${ue}/${n}`}/**
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
 */class Bt{constructor(e){this.auth=e,this.queue=[]}pushCallback(e,t){const n=s=>new Promise((a,c)=>{try{const o=e(s);a(o)}catch(o){c(o)}});n.onAbort=t,this.queue.push(n);const i=this.queue.length-1;return()=>{this.queue[i]=()=>Promise.resolve()}}async runMiddleware(e){if(this.auth.currentUser===e)return;const t=[];try{for(const n of this.queue)await n(e),n.onAbort&&t.push(n.onAbort)}catch(n){t.reverse();for(const i of t)try{i()}catch{}throw this.auth._errorFactory.create("login-blocked",{originalMessage:n==null?void 0:n.message})}}}/**
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
 */async function Kt(r,e={}){return J(r,"GET","/v2/passwordPolicy",fe(r,e))}/**
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
 */const $t=6;class Ht{constructor(e){var t,n,i,s;const a=e.customStrengthOptions;this.customStrengthOptions={},this.customStrengthOptions.minPasswordLength=(t=a.minPasswordLength)!==null&&t!==void 0?t:$t,a.maxPasswordLength&&(this.customStrengthOptions.maxPasswordLength=a.maxPasswordLength),a.containsLowercaseCharacter!==void 0&&(this.customStrengthOptions.containsLowercaseLetter=a.containsLowercaseCharacter),a.containsUppercaseCharacter!==void 0&&(this.customStrengthOptions.containsUppercaseLetter=a.containsUppercaseCharacter),a.containsNumericCharacter!==void 0&&(this.customStrengthOptions.containsNumericCharacter=a.containsNumericCharacter),a.containsNonAlphanumericCharacter!==void 0&&(this.customStrengthOptions.containsNonAlphanumericCharacter=a.containsNonAlphanumericCharacter),this.enforcementState=e.enforcementState,this.enforcementState==="ENFORCEMENT_STATE_UNSPECIFIED"&&(this.enforcementState="OFF"),this.allowedNonAlphanumericCharacters=(i=(n=e.allowedNonAlphanumericCharacters)===null||n===void 0?void 0:n.join(""))!==null&&i!==void 0?i:"",this.forceUpgradeOnSignin=(s=e.forceUpgradeOnSignin)!==null&&s!==void 0?s:!1,this.schemaVersion=e.schemaVersion}validatePassword(e){var t,n,i,s,a,c;const o={isValid:!0,passwordPolicy:this};return this.validatePasswordLengthOptions(e,o),this.validatePasswordCharacterOptions(e,o),o.isValid&&(o.isValid=(t=o.meetsMinPasswordLength)!==null&&t!==void 0?t:!0),o.isValid&&(o.isValid=(n=o.meetsMaxPasswordLength)!==null&&n!==void 0?n:!0),o.isValid&&(o.isValid=(i=o.containsLowercaseLetter)!==null&&i!==void 0?i:!0),o.isValid&&(o.isValid=(s=o.containsUppercaseLetter)!==null&&s!==void 0?s:!0),o.isValid&&(o.isValid=(a=o.containsNumericCharacter)!==null&&a!==void 0?a:!0),o.isValid&&(o.isValid=(c=o.containsNonAlphanumericCharacter)!==null&&c!==void 0?c:!0),o}validatePasswordLengthOptions(e,t){const n=this.customStrengthOptions.minPasswordLength,i=this.customStrengthOptions.maxPasswordLength;n&&(t.meetsMinPasswordLength=e.length>=n),i&&(t.meetsMaxPasswordLength=e.length<=i)}validatePasswordCharacterOptions(e,t){this.updatePasswordCharacterOptionsStatuses(t,!1,!1,!1,!1);let n;for(let i=0;i<e.length;i++)n=e.charAt(i),this.updatePasswordCharacterOptionsStatuses(t,n>="a"&&n<="z",n>="A"&&n<="Z",n>="0"&&n<="9",this.allowedNonAlphanumericCharacters.includes(n))}updatePasswordCharacterOptionsStatuses(e,t,n,i,s){this.customStrengthOptions.containsLowercaseLetter&&(e.containsLowercaseLetter||(e.containsLowercaseLetter=t)),this.customStrengthOptions.containsUppercaseLetter&&(e.containsUppercaseLetter||(e.containsUppercaseLetter=n)),this.customStrengthOptions.containsNumericCharacter&&(e.containsNumericCharacter||(e.containsNumericCharacter=i)),this.customStrengthOptions.containsNonAlphanumericCharacter&&(e.containsNonAlphanumericCharacter||(e.containsNonAlphanumericCharacter=s))}}/**
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
 */class zt{constructor(e,t,n,i){this.app=e,this.heartbeatServiceProvider=t,this.appCheckServiceProvider=n,this.config=i,this.currentUser=null,this.emulatorConfig=null,this.operations=Promise.resolve(),this.authStateSubscription=new Te(this),this.idTokenSubscription=new Te(this),this.beforeStateQueue=new Bt(this),this.redirectUser=null,this.isProactiveRefreshEnabled=!1,this.EXPECTED_PASSWORD_POLICY_SCHEMA_VERSION=1,this._canInitEmulator=!0,this._isInitialized=!1,this._deleted=!1,this._initializationPromise=null,this._popupRedirectResolver=null,this._errorFactory=Me,this._agentRecaptchaConfig=null,this._tenantRecaptchaConfigs={},this._projectPasswordPolicy=null,this._tenantPasswordPolicies={},this.lastNotifiedUid=void 0,this.languageCode=null,this.tenantId=null,this.settings={appVerificationDisabledForTesting:!1},this.frameworks=[],this.name=e.name,this.clientVersion=i.sdkClientVersion}_initializeWithPersistence(e,t){return t&&(this._popupRedirectResolver=P(t)),this._initializationPromise=this.queue(async()=>{var n,i;if(!this._deleted&&(this.persistenceManager=await D.create(this,e),!this._deleted)){if(!((n=this._popupRedirectResolver)===null||n===void 0)&&n._shouldInitProactively)try{await this._popupRedirectResolver._initialize(this)}catch{}await this.initializeCurrentUser(t),this.lastNotifiedUid=((i=this.currentUser)===null||i===void 0?void 0:i.uid)||null,!this._deleted&&(this._isInitialized=!0)}}),this._initializationPromise}async _onStorageEvent(){if(this._deleted)return;const e=await this.assertedPersistence.getCurrentUser();if(!(!this.currentUser&&!e)){if(this.currentUser&&e&&this.currentUser.uid===e.uid){this._currentUser._assign(e),await this.currentUser.getIdToken();return}await this._updateCurrentUser(e,!0)}}async initializeCurrentUser(e){var t;const n=await this.assertedPersistence.getCurrentUser();let i=n,s=!1;if(e&&this.config.authDomain){await this.getOrInitRedirectPersistenceManager();const a=(t=this.redirectUser)===null||t===void 0?void 0:t._redirectEventId,c=i==null?void 0:i._redirectEventId,o=await this.tryRedirectSignIn(e);(!a||a===c)&&(o!=null&&o.user)&&(i=o.user,s=!0)}if(!i)return this.directlySetCurrentUser(null);if(!i._redirectEventId){if(s)try{await this.beforeStateQueue.runMiddleware(i)}catch(a){i=n,this._popupRedirectResolver._overrideRedirectResult(this,()=>Promise.reject(a))}return i?this.reloadAndSetCurrentUserOrClear(i):this.directlySetCurrentUser(null)}return f(this._popupRedirectResolver,this,"argument-error"),await this.getOrInitRedirectPersistenceManager(),this.redirectUser&&this.redirectUser._redirectEventId===i._redirectEventId?this.directlySetCurrentUser(i):this.reloadAndSetCurrentUserOrClear(i)}async tryRedirectSignIn(e){let t=null;try{t=await this._popupRedirectResolver._completeRedirectFn(this,e,!0)}catch{await this._setRedirectUser(null)}return t}async reloadAndSetCurrentUserOrClear(e){try{await re(e)}catch(t){if((t==null?void 0:t.code)!=="auth/network-request-failed")return this.directlySetCurrentUser(null)}return this.directlySetCurrentUser(e)}useDeviceLanguage(){this.languageCode=gt()}async _delete(){this._deleted=!0}async updateCurrentUser(e){const t=e?W(e):null;return t&&f(t.auth.config.apiKey===this.config.apiKey,this,"invalid-user-token"),this._updateCurrentUser(t&&t._clone(this))}async _updateCurrentUser(e,t=!1){if(!this._deleted)return e&&f(this.tenantId===e.tenantId,this,"tenant-id-mismatch"),t||await this.beforeStateQueue.runMiddleware(e),this.queue(async()=>{await this.directlySetCurrentUser(e),this.notifyAuthListeners()})}async signOut(){return await this.beforeStateQueue.runMiddleware(null),(this.redirectPersistenceManager||this._popupRedirectResolver)&&await this._setRedirectUser(null),this._updateCurrentUser(null,!0)}setPersistence(e){return this.queue(async()=>{await this.assertedPersistence.setPersistence(P(e))})}_getRecaptchaConfig(){return this.tenantId==null?this._agentRecaptchaConfig:this._tenantRecaptchaConfigs[this.tenantId]}async validatePassword(e){this._getPasswordPolicyInternal()||await this._updatePasswordPolicy();const t=this._getPasswordPolicyInternal();return t.schemaVersion!==this.EXPECTED_PASSWORD_POLICY_SCHEMA_VERSION?Promise.reject(this._errorFactory.create("unsupported-password-policy-schema-version",{})):t.validatePassword(e)}_getPasswordPolicyInternal(){return this.tenantId===null?this._projectPasswordPolicy:this._tenantPasswordPolicies[this.tenantId]}async _updatePasswordPolicy(){const e=await Kt(this),t=new Ht(e);this.tenantId===null?this._projectPasswordPolicy=t:this._tenantPasswordPolicies[this.tenantId]=t}_getPersistence(){return this.assertedPersistence.persistence.type}_updateErrorMap(e){this._errorFactory=new le("auth","Firebase",e())}onAuthStateChanged(e,t,n){return this.registerStateListener(this.authStateSubscription,e,t,n)}beforeAuthStateChanged(e,t){return this.beforeStateQueue.pushCallback(e,t)}onIdTokenChanged(e,t,n){return this.registerStateListener(this.idTokenSubscription,e,t,n)}authStateReady(){return new Promise((e,t)=>{if(this.currentUser)e();else{const n=this.onAuthStateChanged(()=>{n(),e()},t)}})}async revokeAccessToken(e){if(this.currentUser){const t=await this.currentUser.getIdToken(),n={providerId:"apple.com",tokenType:"ACCESS_TOKEN",token:e,idToken:t};this.tenantId!=null&&(n.tenantId=this.tenantId),await Lt(this,n)}}toJSON(){var e;return{apiKey:this.config.apiKey,authDomain:this.config.authDomain,appName:this.name,currentUser:(e=this._currentUser)===null||e===void 0?void 0:e.toJSON()}}async _setRedirectUser(e,t){const n=await this.getOrInitRedirectPersistenceManager(t);return e===null?n.removeCurrentUser():n.setCurrentUser(e)}async getOrInitRedirectPersistenceManager(e){if(!this.redirectPersistenceManager){const t=e&&P(e)||this._popupRedirectResolver;f(t,this,"argument-error"),this.redirectPersistenceManager=await D.create(this,[P(t._redirectPersistence)],"redirectUser"),this.redirectUser=await this.redirectPersistenceManager.getCurrentUser()}return this.redirectPersistenceManager}async _redirectUserForId(e){var t,n;return this._isInitialized&&await this.queue(async()=>{}),((t=this._currentUser)===null||t===void 0?void 0:t._redirectEventId)===e?this._currentUser:((n=this.redirectUser)===null||n===void 0?void 0:n._redirectEventId)===e?this.redirectUser:null}async _persistUserIfCurrent(e){if(e===this.currentUser)return this.queue(async()=>this.directlySetCurrentUser(e))}_notifyListenersIfCurrent(e){e===this.currentUser&&this.notifyAuthListeners()}_key(){return`${this.config.authDomain}:${this.config.apiKey}:${this.name}`}_startProactiveRefresh(){this.isProactiveRefreshEnabled=!0,this.currentUser&&this._currentUser._startProactiveRefresh()}_stopProactiveRefresh(){this.isProactiveRefreshEnabled=!1,this.currentUser&&this._currentUser._stopProactiveRefresh()}get _currentUser(){return this.currentUser}notifyAuthListeners(){var e,t;if(!this._isInitialized)return;this.idTokenSubscription.next(this.currentUser);const n=(t=(e=this.currentUser)===null||e===void 0?void 0:e.uid)!==null&&t!==void 0?t:null;this.lastNotifiedUid!==n&&(this.lastNotifiedUid=n,this.authStateSubscription.next(this.currentUser))}registerStateListener(e,t,n,i){if(this._deleted)return()=>{};const s=typeof t=="function"?t:t.next.bind(t);let a=!1;const c=this._isInitialized?Promise.resolve():this._initializationPromise;if(f(c,this,"internal-error"),c.then(()=>{a||s(this.currentUser)}),typeof t=="function"){const o=e.addObserver(t,n,i);return()=>{a=!0,o()}}else{const o=e.addObserver(t);return()=>{a=!0,o()}}}async directlySetCurrentUser(e){this.currentUser&&this.currentUser!==e&&this._currentUser._stopProactiveRefresh(),e&&this.isProactiveRefreshEnabled&&e._startProactiveRefresh(),this.currentUser=e,e?await this.assertedPersistence.setCurrentUser(e):await this.assertedPersistence.removeCurrentUser()}queue(e){return this.operations=this.operations.then(e,e),this.operations}get assertedPersistence(){return f(this.persistenceManager,this,"internal-error"),this.persistenceManager}_logFramework(e){!e||this.frameworks.includes(e)||(this.frameworks.push(e),this.frameworks.sort(),this.clientVersion=ze(this.config.clientPlatform,this._getFrameworks()))}_getFrameworks(){return this.frameworks}async _getAdditionalHeaders(){var e;const t={"X-Client-Version":this.clientVersion};this.app.options.appId&&(t["X-Firebase-gmpid"]=this.app.options.appId);const n=await((e=this.heartbeatServiceProvider.getImmediate({optional:!0}))===null||e===void 0?void 0:e.getHeartbeatsHeader());n&&(t["X-Firebase-Client"]=n);const i=await this._getAppCheckToken();return i&&(t["X-Firebase-AppCheck"]=i),t}async _getAppCheckToken(){var e;const t=await((e=this.appCheckServiceProvider.getImmediate({optional:!0}))===null||e===void 0?void 0:e.getToken());return t!=null&&t.error&&ht(`Error while retrieving App Check token: ${t.error}`),t==null?void 0:t.token}}function Ge(r){return W(r)}class Te{constructor(e){this.auth=e,this.observer=null,this.addObserver=rt(t=>this.observer=t)}get next(){return f(this.observer,this.auth,"internal-error"),this.observer.next.bind(this.observer)}}function Gt(r,e){const t=(e==null?void 0:e.persistence)||[],n=(Array.isArray(t)?t:[t]).map(P);e!=null&&e.errorMap&&r._updateErrorMap(e.errorMap),r._initializeWithPersistence(n,e==null?void 0:e.popupRedirectResolver)}/**
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
 */class ne{constructor(e){this.user=e.user,this.providerId=e.providerId,this._tokenResponse=e._tokenResponse,this.operationType=e.operationType}static async _fromIdTokenResponse(e,t,n,i=!1){const s=await U._fromIdTokenResponse(e,n,i),a=be(n);return new ne({user:s,providerId:a,_tokenResponse:n,operationType:t})}static async _forOperation(e,t,n){await e._updateTokensIfNecessary(n,!0);const i=be(n);return new ne({user:e,providerId:i,_tokenResponse:n,operationType:t})}}function be(r){return r.providerId?r.providerId:"phoneNumber"in r?"phone":null}/**
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
 */async function Wt(r,e){return vt(r,"POST","/v1/accounts:signInWithCustomToken",fe(r,e))}/**
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
 */async function jt(r,e){const t=Ge(r),n=await Wt(t,{token:e,returnSecureToken:!0}),i=await ne._fromIdTokenResponse(t,"signIn",n);return await t._updateCurrentUser(i.user),i}function Jt(r,e,t,n){return W(r).onAuthStateChanged(e,t,n)}new j(3e4,6e4);/**
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
 */new j(2e3,1e4);/**
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
 */new j(3e4,6e4);/**
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
 */new j(5e3,15e3);var Ae="@firebase/auth",ke="1.6.0";/**
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
 */class Yt{constructor(e){this.auth=e,this.internalListeners=new Map}getUid(){var e;return this.assertAuthConfigured(),((e=this.auth.currentUser)===null||e===void 0?void 0:e.uid)||null}async getToken(e){return this.assertAuthConfigured(),await this.auth._initializationPromise,this.auth.currentUser?{accessToken:await this.auth.currentUser.getIdToken(e)}:null}addAuthTokenListener(e){if(this.assertAuthConfigured(),this.internalListeners.has(e))return;const t=this.auth.onIdTokenChanged(n=>{e((n==null?void 0:n.stsTokenManager.accessToken)||null)});this.internalListeners.set(e,t),this.updateProactiveRefresh()}removeAuthTokenListener(e){this.assertAuthConfigured();const t=this.internalListeners.get(e);t&&(this.internalListeners.delete(e),t(),this.updateProactiveRefresh())}assertAuthConfigured(){f(this.auth._initializationPromise,"dependent-sdk-initialized-before-auth")}updateProactiveRefresh(){this.internalListeners.size>0?this.auth._startProactiveRefresh():this.auth._stopProactiveRefresh()}}/**
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
 */function Xt(r){switch(r){case"Node":return"node";case"ReactNative":return"rn";case"Worker":return"webworker";case"Cordova":return"cordova";case"WebExtension":return"web-extension";default:return}}function Qt(r){pe(new me("auth",(e,{options:t})=>{const n=e.getProvider("app").getImmediate(),i=e.getProvider("heartbeat"),s=e.getProvider("app-check-internal"),{apiKey:a,authDomain:c}=n.options;f(a&&!a.includes(":"),"invalid-api-key",{appName:n.name});const o={apiKey:a,authDomain:c,clientPlatform:r,apiHost:"identitytoolkit.googleapis.com",tokenApiHost:"securetoken.googleapis.com",apiScheme:"https",sdkClientVersion:ze(r)},h=new zt(n,i,s,o);return Gt(h,t),h},"PUBLIC").setInstantiationMode("EXPLICIT").setInstanceCreatedCallback((e,t,n)=>{e.getProvider("auth-internal").initialize()})),pe(new me("auth-internal",e=>{const t=Ge(e.getProvider("auth").getImmediate());return(n=>new Yt(n))(t)},"PRIVATE").setInstantiationMode("EXPLICIT")),ge(Ae,ke,Xt(r)),ge(Ae,ke,"esm2017")}/**
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
 */const Zt=5*60;Ze("authIdTokenMaxAge");Qt("Browser");const qt=async({adId:r,boardIds:e})=>{const t=e[0]||"",n={adId:r,userId:m.currentUser.uid,boardId:t,boardIds:e},i=await O.post("getTiktokLibraryAd",n);return i?i.data:{}},er={scrape:qt},tr=async({adUrl:r,boardIds:e})=>{const t=e[0]||"",n={adUrl:r,userId:m.currentUser.uid,boardId:t,boardIds:e},i=await O.post("getTiktokAd",n);return i?i.data:{}},rr={scrape:tr},nr=async(r,e,t,n)=>{const i={adUrl:r,videoUrl:e,userId:t,boardId:n},s=await O.post("/getTiktokCreativeCenterSpotlightAd",i);return s?s.data:{}},ir={scrape:nr},sr=async({adUrl:r,videoUrl:e,boardIds:t,industry:n})=>{const i=t[0]||"",s=m.currentUser.uid,a={adUrl:r,videoUrl:e,boardId:i,boardIds:t,userId:s,industry:n},c=await O.post("getTiktokCreativeCenterAd",a);return c?c.data:{}},ar={scrape:sr},$={AdLibrary:er,Organic:rr,Spotlight:ir,TopAds:ar},or=async({adId:r,boardIds:e,pageId:t,adJson:n,adUrl:i})=>{const s=m.currentUser.uid,a=e[0]||"",c={ad_url:i||`https://www.facebook.com/ads/library/?id=${r}`,adId:r,user_id:s,board_id:a,boardIds:e,adLibraryId:t,adJson:n},o=await O.post("getFBAdChromeExtensionHTML",c);return o?o.data:{}},cr={scrape:or},dr={AdLibrary:cr},ur=async r=>{const e=m.currentUser,t=M(C(T,"ads"),L("created_by","==",e.uid),L("ad_id","==",r)),n=await F(t),i={savedBefore:!n.empty};return n.empty||(i.id=n.docs[0].id,i.boardIds=n.docs[0].data().board_ids),i},lr={checkSavedBefore:ur},hr=async()=>{const r=m.currentUser,e=M(C(T,"boards"),L("created_by","==",r.uid)),t=await F(e);if(t.empty)return[];const n=t.docs.map(a=>({id:a.id,...a.data()||[]})),i=await We();return n.map(a=>{var c;return{...a,adCount:((c=i.find(o=>o.key===a.id))==null?void 0:c.doc_count)||0}}).sort((a,c)=>a.name>c.name?1:-1)},fr=async r=>{if(!m.currentUser)return[];if(r){const t=M(C(T,"boards"),L("teamId","==",r.id)),n=await F(t);if(n.empty)return[];const i=n.docs.map(c=>({id:c.id,...c.data()||[]})),s=await We(r.id);return i.map(c=>{var o;return{...c,adCount:((o=s.find(h=>h.key===c.id))==null?void 0:o.doc_count)||0}}).sort((c,o)=>c.name>o.name?1:-1)}},pr=async(r,e=null)=>{const t=m.currentUser;r.created_by=t.uid;const{id:n}=await Ue(C(T,"boards"),r);if(e){const i={boardIds:e.boardIds?[...e.boardIds,n]:[n]};await w.Folders.update(e.id,i)}return n},We=async(r=null)=>{const e=r?{teamId:r}:{},t=await Le.post("ads/board/count",e);return t.count?t.count:0},mr={getAll:hr,getAllByTeam:fr,create:pr},gr=async()=>{const r=m.currentUser,e=ie(T,"auth-users",r.uid),t=await Oe(e);if(t.exists())return{...t.data(),id:t.id};console.log("No such document!")},_r=async r=>{const e=m.currentUser,t=ie(T,"auth-users",e.uid);return Re(t,r)},yr={get:gr,update:_r},Ir=async()=>{const r=m.currentUser,e=M(C(T,"folders"),L("createdBy","==",r.uid)),t=await F(e);return t.empty?[]:t.docs.map(n=>({...n.data(),id:n.id}))},vr=async r=>{if(!m.currentUser)return[];const t=M(C(T,"folders"),L("teamId","==",r)),n=await F(t);return n.empty?[]:n.docs.map(i=>({...i.data(),id:i.id}))},wr=(r,e)=>{const t=[...e];let n=r.map(s=>({...s,boards:[]}));const i={name:"Default Folder",id:"default",boards:[],briefs:[],spyders:[]};for(;t.length;){const s=t.pop(),a=n.findIndex(c=>c.boardIds.includes(s.id));a!==-1?n[a].boards.push(s):i.boards.push(s)}return n.unshift(i),n=n.map(s=>{const a={...s};return a.boards=a.boards.sort((c,o)=>c.name<o.name?-1:1),a}),n},Tr=async(r,e)=>{const t=ie(T,"folders",r);await Re(t,e)},br=async r=>{const e=m.currentUser;r.createdBy=e.uid;const{id:t}=await Ue(C(T,"folders"),r);return t},Ar={getAll:Ir,getFolderItems:wr,getAllByTeam:vr,update:Tr,create:br},kr=async()=>{var n;const r=m.currentUser;if(!r)return[];const e=M(C(T,"teams"),L("members","array-contains",r.uid)),t=await F(e);return((n=t.docs)==null?void 0:n.length)===0?null:{id:t.docs[0].id,...t.docs[0].data()||[]}},Sr={getUserTeam:kr},Er={async getById(r){try{const e=ie(T,"extension-gpt-promps",r),t=await Oe(e);if(t.exists())return t.data();throw new Error(`Prompt with ID ${r} not found`)}catch(e){throw console.error("Error fetching GPT prompt:",e),e}}},w={Ads:lr,Boards:mr,Folders:Ar,Users:yr,Teams:Sr,GptPrompts:Er},Cr=async r=>{const e={adHTML:r.html,metadata:{url:r.url,adID:r.url.match(/(\d+)/)[1],boardId:r.boardIds[0],userId:r.userId},boardIds:r.boardIds,userId:r.userId};return I(e),O.post("getLinkedInAdExtension",e)},Se={scrape:Cr},Nr=async r=>{const e={avatarUrl:r.avatarUrl,name:r.name,description:r.description,url:r.url,boardIds:r.boardIds,userId:r.userId};return console.log("adData",e),O.post("getYoutubeShorts",e)},Pr={scrape:Nr},Ur=r=>{Le.post("extension/log",r).then(e=>{console.log("Server log sent successfully")}).catch(e=>{console.error("Error sending server log:",e)})},Lr={send:Ur};let y={},_={},je=!1,Or="https://app.foreplay.co/auth-extension";const q="https://www.foreplay.co/extension/uninstall",E={"brand-lookup":null,"brand-preview":null};Jt(m,async function(r){I("onAuthStateChanged",+new Date),y=r,r?(console.log("user",r),console.log("user.uid",r.uid),_=await w.Users.get(),console.log("AuthUser",_),g.value.user={..._},chrome.storage.local.set({[_e.USER]:_}),Ye(),Je(_)):(_={},g.value.user={},chrome.storage.local.set({[_e.USER]:null}),chrome.runtime.setUninstallURL(q)),console.log("User state change detected from the Background script of the Chrome Extension:",r),je=!0});async function Je(r){try{const e=await chrome.tabs.query({});console.log("Attempting to notify content scripts of auth change",e==null?void 0:e.length,"tabs");for(const t of e)try{await chrome.tabs.sendMessage(t.id,{type:"auth-state-changed",user:r,authenticated:!!r}),console.log("Notified tab",t.id,`(${(t==null?void 0:t.url)||"unknown"})`,"of auth state change")}catch{console.log("Skipping tab",t.id,`(${(t==null?void 0:t.url)||"unknown"})`)}}catch(e){I.error("Error notifying content scripts of auth change:",e)}}async function Q(){_=await w.Users.get(),console.log("AuthUser",_),g.value.user={..._}}chrome.runtime.onMessageExternal.addListener(async function(r,e,t){console.log("auth request",r),r.event==="auth"&&(console.log("in auth statement"),await jt(m,r.data.token.data),Je(m.currentUser),t({status:"worked"}))});function Rr(r){return{FACEBOOK_AD_LIBRARY:dr.AdLibrary.scrape,TIKTOK_TOP_ADS:$.TopAds.scrape,TIKTOK_SPOTLIGHT:$.Spotlight.scrape,TIKTOK_AD_LIBRARY:$.AdLibrary.scrape,TIKTOK_ORGANIC:$.Organic.scrape,TIKTOK_ORGANIC_FEED:$.Organic.scrape,LINKEDIN_AD_LIBRARY:Se.scrape,LINKEDIN_AD_DETAILS:Se.scrape,INSTAGRAM:ut.scrape,YOUTUBE_SHORTS:Pr.scrape}[r]}function Ye(){var n,i;const r=((n=g.value.user)==null?void 0:n.email)??(_==null?void 0:_.email)??((i=m.currentUser)==null?void 0:i.email),e=new URLSearchParams;r&&e.set("email",r);const t=e.toString()?`${q}?${e.toString()}`:q;if(t.length>255){chrome.runtime.setUninstallURL(q);return}chrome.runtime.setUninstallURL(t)}chrome.sidePanel.setPanelBehavior({openPanelOnActionClick:!0}).catch(r=>I.error("Failed to set side panel behavior:",r));chrome.runtime.onInstalled.addListener(function(r){Ye(),r.reason==="install"&&chrome.tabs.create({url:"https://www.foreplay.co/extension/install"})});chrome.runtime.onMessage.addListener(function(r,e,t){return(async()=>{var n,i,s,a,c,o,h,v,b,x,V,B,K,R;if(I("[EVENT]",`[${r.type}]`,r),r.type=="scrape"){if((!y||!(y!=null&&y.uid))&&!((n=g.value.user)!=null&&n.id))if((s=(i=m)==null?void 0:i.currentUser)==null?void 0:s.uid)await Q();else{t({status:"no-auth"}),chrome.tabs.create({url:Or});return}const d=Rr(r.data.scrapeJob);try{I(r.data);const u=await d(r.data);t({type:"scrape:success",payload:u==null?void 0:u.payload})}catch(u){I.error(u),t({type:"scrape:error",message:u})}}else if(r.type==="ready-status")t({type:"ready-status:success",ready:je});else if(r.type==="open-window")chrome.tabs.create({url:r.data.url});else if(r.type==="open-side-panel"){const d=(a=e.tab)==null?void 0:a.id,u=(c=e.tab)==null?void 0:c.windowId;try{d!=null?await chrome.sidePanel.open({tabId:d}):u!=null&&await chrome.sidePanel.open({windowId:u}),t({type:"open-side-panel:success"})}catch(l){I.error("Failed to open side panel:",l),t({type:"open-side-panel:error",message:l.message})}}else if(r.type==="get-boards"){_=await w.Users.get(),g.value.user={..._};const d=await w.Teams.getUserTeam();g.value.team={...d};let u,l;d?(u=await w.Boards.getAllByTeam(d),g.value.boards=u,l=await w.Folders.getAllByTeam(d.id),g.value.folders=l):(u=await w.Boards.getAll(),g.value.boards=u,l=await w.Folders.getAll(),g.value.folders=l),g.value.defaultBoards=g.value.boards.filter(N=>{var k;return(k=g.value.user.defaultBoards)==null?void 0:k.includes(N.id)});const p=w.Folders.getFolderItems(l,u),Y=u.filter(N=>{var k;return(k=_.starredBoards)==null?void 0:k.includes(N.id)});t({status:"get-boards:success",data:{boards:u,folders:p,starredBoards:Y}})}else if(r.type==="create-board"){const{payload:d,folder:u}=r.data,l=g.value.team;l&&Object.keys(l).length&&(d.teamId=l.id);try{const p=await w.Boards.create(d,u);t({type:"create-board:success",boardId:p})}catch(p){I.error(p),t({type:"create-board:error",error:p})}}else if(r.type==="create-folder"){const{payload:d}=r.data,u=g.value.team;u&&Object.keys(u).length&&(d.teamId=u.id);try{const l=await w.Folders.create(d);t({type:"create-folder:success",folderId:l})}catch(l){I.error(l),t({type:"create-folder:error",error:l})}}else if(r.type=="check-ad-saved"){const d=await w.Ads.checkSavedBefore(r.data.adId);t({type:"check-ad-saved:success",...d})}else if(r.type==="fetch-user-id")t({type:"fetch-user-id:success",uid:g.value.user.id});else if(r.type==="auth-token")try{const u=await st(at,"createToken")({uid:g.value.user.id});t({type:"auth-token:success",token:u})}catch(d){I.error(d),t({type:"auth-token:error",error:d})}else if(r.type==="get-user")t({type:"get-user:success",authUser:_});else if(r.type==="update-user")try{await w.Users.update(r.data),t({type:"update-user:success",authUser:_})}catch(d){I.error(d),t({type:"update-user:error",error:d})}else if(r.type==="set-last-seen-extension")await w.Users.update({lastSeenExtension:ot()}).then(()=>console.log("🆕 lastSeenExtension updated")).catch(d=>console.error("Error updating lastSeenExtension:",d)),t({type:"set-last-seen-extension:success",authUser:_});else if(r.type==="get-chatgpt-prompt"){if((!y||!(y!=null&&y.uid))&&!((o=g.value.user)!=null&&o.id))if((v=(h=m)==null?void 0:h.currentUser)==null?void 0:v.uid)await Q();else{t({type:"get-chatgpt-prompt:error",error:"User not authenticated"});return}try{const d=await w.GptPrompts.getById(r.data.promptId);console.log("promptData",d),t({type:"get-chatgpt-prompt:success",data:{prompt:d.prompt,image:d.image,video:d.video}})}catch(d){I.error(d),t({type:"get-chatgpt-prompt:error",error:d.message})}}else if(r.type==="fetch-image"){try{const u=await(await fetch(r.data.imageUrl)).blob(),l=new FileReader;l.readAsDataURL(u),l.onloadend=function(){const p=l.result.split(",")[1];t({type:"fetch-image:success",data:p})}}catch(d){I.error(d),t({type:"fetch-image:error",error:d.message})}return!0}else if(r.type==="brand-lookup"){if((!y||!(y!=null&&y.uid))&&!((b=g.value.user)!=null&&b.id))if((V=(x=m)==null?void 0:x.currentUser)==null?void 0:V.uid)await Q();else{t({type:"brand-lookup:error",message:"Not authenticated"});return}const{id:d,type:u}=r.data??{};if(!d||!u){t({type:"brand-lookup:error",message:"Invalid lookup payload"});return}E["brand-lookup"]&&E["brand-lookup"].abort();const l=new AbortController;E["brand-lookup"]=l;try{const p=await ct(r.data,l.signal);t({type:"brand-lookup:success",data:p})}catch(p){if(p.name==="AbortError"){t({type:"brand-lookup:cancelled"});return}I.error(p),t({type:"brand-lookup:error",message:p.message})}finally{E["brand-lookup"]=null}}else if(r.type==="brand-preview"){if((!y||!(y!=null&&y.uid))&&!((B=g.value.user)!=null&&B.id))if((R=(K=m)==null?void 0:K.currentUser)==null?void 0:R.uid)await Q();else{t({type:"brand-preview:error",message:"Not authenticated"});return}const{id:d,type:u}=r.data??{};if(!d||!u){t({type:"brand-preview:error",message:"Invalid lookup payload"});return}E["brand-preview"]&&E["brand-preview"].abort();const l=new AbortController;E["brand-preview"]=l;try{const p=await dt(r.data,l.signal);t({type:"brand-preview:success",data:p})}catch(p){if(p.name==="AbortError"){t({type:"brand-preview:cancelled"});return}I.error(p),t({type:"brand-preview:error",message:p.message})}finally{E["brand-preview"]=null}}else if(r.type==="log")try{Lr.send(r.data)}catch(d){I.error(d)}})(),!0});
