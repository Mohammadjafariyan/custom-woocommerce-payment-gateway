(()=>{"use strict";const e=window.React,t=window.wc.wcBlocksRegistry,r=window.wp.i18n,n=window.wc.wcSettings,o=window.wp.htmlEntities;var c;const s=(0,n.getPaymentMethodData)("CustomCheque",{}),a=(0,r.__)("Check payment","woo-gutenberg-products-block"),i=(0,o.decodeEntities)(s?.title||"")||a,l=()=>(0,o.decodeEntities)(s.description||""),d={name:"CustomCheque",label:(0,e.createElement)((t=>{const{PaymentMethodLabel:r}=t.components;return(0,e.createElement)(r,{text:i})}),null),content:(0,e.createElement)(l,null),edit:(0,e.createElement)(l,null),canMakePayment:()=>!0,ariaLabel:i,supports:{features:null!==(c=s?.supports)&&void 0!==c?c:[]}};console.trace("registerrrrrrrrrrrrrrrrrrrrrrrred registerPaymentMethod"),(0,t.registerPaymentMethod)(d)})();