(()=>{"use strict";const t=window.wp.htmlEntities,e=window.ReactJSXRuntime,{registerPaymentMethod:i}=window.wc.wcBlocksRegistry,{getSetting:s}=window.wc.wcSettings,n=s("china_payments_stripe_wechat_data",{}),a=(0,t.decodeEntities)(n.title),c=()=>(0,t.decodeEntities)(n.description||""),o=()=>n.icon?(0,e.jsx)("img",{src:n.icon,style:{float:"right",marginRight:"20px"}}):"",w=()=>(0,e.jsxs)("span",{style:{width:"100%"},children:[a,(0,e.jsx)(o,{})]});i({name:"china_payments_stripe_wechat",label:(0,e.jsx)(w,{}),content:(0,e.jsx)(c,{}),edit:(0,e.jsx)(c,{}),canMakePayment:()=>!0,ariaLabel:a,supports:{features:n.supports}})})();