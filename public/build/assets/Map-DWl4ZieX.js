const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["assets/leaflet-src-BKzU_FhC.js","assets/leaflet-src-yBFDMfNl.js","assets/leaflet-CLLlcC51.css"])))=>i.map(i=>d[i]);
import{s as T,D as ne,p as oe,o as re,d as ie,E as ue,b as l,H as pe,x as ce,v as de,w as i,q as N,f as R,F as D,t as P,G as y,A as E,k as V,_ as B,c as h}from"./app-BXcmAtgY.js";import{_ as me}from"./AppLayout-DfnswxZg.js";const ve={class:"content-wrapper wells-map-page"},fe={class:"page-header"},be={class:"well-map-layers card","aria-label":"طبقات خريطة الآبار"},Ae={class:"well-map-layers-header"},_e={class:"well-map-layer-groups"},ge={class:"well-map-layer-group"},we=["value"],ke={class:"well-map-layer-group"},Te=["value"],Ne={class:"well-map-inactive-toggle"},I="__unknown__",he={__name:"Map",props:{wells:{type:Array,default:()=>[]}},setup(G){const b=G,u=[{value:"يعمل",label:"يعمل",className:"is-working"},{value:"متوقف",label:"متوقف",className:"is-stopped"},{value:"تحت الصيانة",label:"تحت الصيانة",className:"is-maintenance"},{value:I,label:"غير محدد",className:"is-unknown"}],r=[{value:"سليمة",label:"سليمة",className:"is-safe"},{value:"متضررة",label:"متضررة",className:"is-damaged"},{value:"أعيد تأهيلها",label:"أعيد تأهيلها",className:"is-rehabilitated"},{value:"مدمرة",label:"مدمرة",className:"is-destroyed"},{value:I,label:"غير محدد",className:"is-unknown"}],W={centrifugal:"رأسي",submersible:"غاطس",vertical:"رأسي"},p=T(u.map(e=>e.value)),c=T(r.map(e=>e.value)),d=T(!0);let m=null,v=null,g=null,C=[],t=null;function A(e){return e===!0||e===1||e==="1"}function _(e){const a=Number(e.LATITUDE),s=Number(e.LONGITUDE);return Number.isFinite(a)&&Number.isFinite(s)&&a>=-90&&a<=90&&s>=-180&&s<=180}function k(e,a){return a.some(s=>s.value===e)?e:I}function S(e){return k(e.OPERATIONAL_STATUS,u)}function L(e){return k(e.DAMAGE_STATUS,r)}function M(e){return _(e)&&(d.value||A(e.IS_ACTIVE))&&p.value.includes(S(e))&&c.value.includes(L(e))}const Z=h(()=>b.wells.filter(_).length),z=h(()=>b.wells.filter(M).length),F=h(()=>b.wells.filter(e=>_(e)&&!A(e.IS_ACTIVE)).length);function $(e,a){const s=e==="operational"?u:r,o=e==="operational"?"OPERATIONAL_STATUS":"DAMAGE_STATUS";return b.wells.filter(f=>_(f)&&k(f[o],s)===a).length}function n(e){return String(e??"").replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;").replaceAll("'","&#039;")}function Y(e){return e.SOURCE_WELL_CODE||e.WELL_NUM||"غير متوفر"}function q(e){return(e.MAP_GENERATORS||e.map_generators||[]).map(a=>a.NUMBER??a.number).filter(a=>a!=null&&a!=="")}function U(e){var a;return((a=u.find(s=>s.value===S(e)))==null?void 0:a.className)||"is-unknown"}function O(e){var a;return((a=r.find(s=>s.value===L(e)))==null?void 0:a.className)||"is-unknown"}function w(e,a=!1){const s=n(e.NAME||"بئر بدون اسم"),o=A(e.IS_ACTIVE)?"":" is-record-inactive",f=a?" is-selected":"";return m.divIcon({className:"well-map-div-icon",html:`
            <div class="well-map-marker ${U(e)}${o}${f}">
                <span class="well-map-marker-symbol" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 2.5C9.6 6.2 5.5 10.3 5.5 15a6.5 6.5 0 0 0 13 0c0-4.7-4.1-8.8-6.5-12.5Z"></path>
                        <path class="well-map-marker-wave" d="M8.4 15.2c1.3-.9 2.4-.9 3.6 0 1.2.9 2.3.9 3.6 0"></path>
                    </svg>
                    <i class="well-map-damage-dot ${O(e)}"></i>
                </span>
                <span class="well-map-marker-label">${s}</span>
            </div>
        `,iconSize:[180,48],iconAnchor:[24,42],popupAnchor:[0,-42]})}function H(e){const a=q(e),s=a.length?a.join("، "):"غير مرتبط بمولد",o=a.length?"ltr":"rtl",f=e.OPERATIONAL_STATUS||"غير محدد",ae=e.DAMAGE_STATUS||"غير محدد",le=W[e.PUMP_TYPE]||e.PUMP_TYPE||"غير محدد",se=e.CAPACITY?`${e.CAPACITY} م³`:"غير متوفر",te=`/wells/wells/${encodeURIComponent(e.OID)}/edit`;return`
        <article class="well-map-popup-card">
            <header class="well-map-popup-header">
                <div>
                    <span class="well-map-popup-number" dir="ltr">${n(Y(e))}</span>
                    <h3>${n(e.NAME||"بئر بدون اسم")}</h3>
                </div>
                <span class="well-map-popup-record-status ${A(e.IS_ACTIVE)?"is-active":"is-inactive"}">
                    ${A(e.IS_ACTIVE)?"مفعّل":"غير مفعّل"}
                </span>
            </header>

            <div class="well-map-popup-statuses">
                <span class="${U(e)}">${n(f)}</span>
                <span class="${O(e)}">${n(ae)}</span>
            </div>

            <dl class="well-map-popup-details">
                <div>
                    <dt>المنطقة</dt>
                    <dd>${n(e.QUARTER_NAME||"غير محددة")}</dd>
                </div>
                <div>
                    <dt>نوع المضخة</dt>
                    <dd>${n(le)}</dd>
                </div>
                <div class="is-wide">
                    <dt>رقم المولد</dt>
                    <dd class="well-map-popup-generator" dir="${o}">${n(s)}</dd>
                </div>
                <div>
                    <dt>الإنتاجية</dt>
                    <dd>${n(se)}</dd>
                </div>
            </dl>

            <a class="well-map-popup-action" href="${te}">
                عرض وتعديل بيانات البئر
            </a>
        </article>
    `}function j(){t&&(t.marker.setIcon(w(t.well)),t.marker.setZIndexOffset(0),t=null)}function Q(e){t&&t!==e&&(t.marker.setIcon(w(t.well)),t.marker.setZIndexOffset(0)),t=e,e.marker.setIcon(w(e.well,!0)),e.marker.setZIndexOffset(1e3)}function x(){g&&(g.clearLayers(),C.forEach(e=>{M(e.well)?e.marker.addTo(g):t===e&&(t=null)}))}function J(){C=b.wells.filter(_).map(e=>{const a=m.marker([Number(e.LATITUDE),Number(e.LONGITUDE)],{icon:w(e),keyboard:!0}),s={well:e,marker:a};return a.bindPopup(H(e),{className:"well-map-popup",minWidth:300,maxWidth:360,closeButton:!0}),a.bindTooltip(n(e.NAME||"بئر بدون اسم"),{direction:"top",offset:[0,-34],className:"well-map-tooltip"}),a.on("click",()=>Q(s)),a.on("popupclose",()=>{t===s&&j()}),s}),x()}async function K(){const e=await B(()=>import("./leaflet-src-BKzU_FhC.js").then(a=>a.l),__vite__mapDeps([0,1]));await B(()=>Promise.resolve({}),__vite__mapDeps([2])),m=e.default,v=m.map("wells-map",{zoomControl:!0,minZoom:10,maxZoom:19}).setView([31.5,34.47],12),m.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"© OpenStreetMap",maxZoom:19}).addTo(v),g=m.layerGroup().addTo(v),J()}function X(){p.value=u.map(e=>e.value),c.value=r.map(e=>e.value),d.value=!0}function ee(){p.value=["يعمل"],c.value=r.map(e=>e.value),d.value=!1}return ne([p,c,d],x,{deep:!0}),oe(K),re(()=>{v&&(v.remove(),v=null)}),(e,a)=>(N(),ie(me,{title:"خريطة الآبار"},{default:ue(()=>[l("div",ve,[l("div",fe,[a[4]||(a[4]=l("div",null,[l("h1",{class:"page-title"},"خريطة الآبار"),l("p",{class:"page-subtitle"},"استعراض الآبار حسب الحالة التشغيلية وحالة الضرر")],-1)),l("a",{href:"/wells/wells",class:"btn btn-secondary",onClick:a[0]||(a[0]=pe(s=>ce(de).visit("/wells/wells"),["prevent"]))}," → قائمة الآبار ")]),l("section",be,[l("div",Ae,[l("div",null,[a[5]||(a[5]=l("span",{class:"well-map-layers-eyebrow"},"طبقات الخريطة",-1)),l("strong",null,i(z.value)+" من "+i(Z.value)+" بئر ظاهر",1)]),l("div",{class:"well-map-layer-actions"},[l("button",{type:"button",class:"btn btn-secondary btn-sm",onClick:ee}," العاملة فقط "),l("button",{type:"button",class:"btn btn-primary btn-sm",onClick:X}," عرض الكل ")])]),l("div",_e,[l("fieldset",ge,[a[6]||(a[6]=l("legend",null,"الحالة التشغيلية",-1)),(N(),R(D,null,P(u,s=>l("label",{key:s.value,class:"well-map-layer-option"},[y(l("input",{"onUpdate:modelValue":a[1]||(a[1]=o=>p.value=o),type:"checkbox",value:s.value},null,8,we),[[E,p.value]]),l("span",{class:V(["well-map-layer-dot",s.className])},null,2),l("span",null,i(s.label),1),l("small",null,i($("operational",s.value)),1)])),64))]),l("fieldset",ke,[a[7]||(a[7]=l("legend",null,"حالة الضرر",-1)),(N(),R(D,null,P(r,s=>l("label",{key:s.value,class:"well-map-layer-option"},[y(l("input",{"onUpdate:modelValue":a[2]||(a[2]=o=>c.value=o),type:"checkbox",value:s.value},null,8,Te),[[E,c.value]]),l("span",{class:V(["well-map-layer-dot",s.className])},null,2),l("span",null,i(s.label),1),l("small",null,i($("damage",s.value)),1)])),64))]),l("label",Ne,[y(l("input",{"onUpdate:modelValue":a[3]||(a[3]=s=>d.value=s),type:"checkbox"},null,512),[[E,d.value]]),l("span",null,[a[8]||(a[8]=l("strong",null,"إظهار السجلات غير المفعّلة",-1)),l("small",null,i(F.value)+" بئر",1)])])])]),a[9]||(a[9]=l("div",{class:"card well-map-card"},[l("div",{id:"wells-map",class:"wells-map-canvas"})],-1))])]),_:1}))}};export{he as default};
