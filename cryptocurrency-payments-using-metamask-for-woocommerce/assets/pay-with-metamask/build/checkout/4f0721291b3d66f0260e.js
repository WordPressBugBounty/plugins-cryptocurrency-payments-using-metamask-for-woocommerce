"use strict";(self.webpackChunkpay_with_metamask=self.webpackChunkpay_with_metamask||[]).push([[901],{87901:(e,t,n)=>{n.r(t),n.d(t,{default:()=>y});var c=n(99196),a=n(26673),s=n(11891),r=n(83215),l=n(43143),o=n(6202),m=n(65645),i=n(30202),p=n(70987);const{const_msg:u,networkName:d}=connect_wallts,w=({currentchain:e,config:t,switchModal:n,switchHandler:r})=>{const[l,m]=(0,c.useState)(!1),{open:i,setOpen:w,openSwitchNetworks:_}=(0,o.dd)(),{isConnected:h,address:E,chain:k}=(0,a.m)(),{disconnect:y}=(0,s.q)();if(!k&&h&&n&&(i&&setTimeout((()=>{r()}),100),_()),(0,c.useEffect)((()=>{k?.id===e.networks.id&&w(!1),i||m(!1)}),[k?.id,i]),i){const e=document.querySelector(".sc-dcJsrY div"),t=document.querySelector(".sc-imWYAI"),n=document.querySelector("#__CONNECTKIT__ button.sc-bypJrT");n&&!l&&(n.click(),m(!0)),t&&"Switch Networks"==e.firstChild.textContent&&(t.textContent=u.switch_network_msg)}return(0,c.createElement)(c.Fragment,null,!h&&(0,c.createElement)("div",{className:"cpmw_selected_wallet"},(0,c.createElement)("div",{className:"cpmw_p_network"},(0,c.createElement)("strong",null,u.select_network,":"),d)),k&&h&&(0,c.createElement)(c.Fragment,null,(0,c.createElement)("div",{className:"cpmw_p_connect"},(0,c.createElement)("div",{className:"cpmw_p_status"},u.connected),(0,c.createElement)("div",{className:"cpmw_disconnect_wallet",onClick:()=>{y()}},u.disconnect)),(0,c.createElement)("div",{className:"cpmw_p_info"},(0,c.createElement)("div",{className:"cpmw_address_wrap"},(0,c.createElement)("strong",null,u.wallet,":"),(0,c.createElement)("span",{className:"cpmw_p_address"},E)),(0,c.createElement)("div",{className:"cpmw_p_network"},(0,c.createElement)("strong",null,u.network,":")," ",e.networkResponse.decimal_networks[k?.id]?e.networkResponse.decimal_networks[k?.id]:k.name))),(0,c.createElement)(p.D8,{data:e,const_msg:u,config:t}),!h&&(0,c.createElement)(p.PP,{const_msg:u}))},_=new m.S,h=e=>{try{const[t,n]=(0,c.useState)(null),[a,s]=(0,c.useState)(!0),m=()=>{s(!1)};return(0,c.useEffect)((()=>{const t={appName:"Pay With Metamask",appDescription:window.location.host,chains:e.networks,appUrl:window.location.host,appIcon:"https://family.co/logo.png"};n((e=>{const t=(0,o._K)({appName:e.appName,chains:[e.chains],appDescription:e.appDescription,appUrl:e.appUrl,appIcon:e.appIcon});if(t){const e=[];e.push("metaMask");const n=t.connectors.filter((t=>{if(e.includes(t.id))return e.includes(t.id)}));return t.connectors=n,(0,r._)(t)}})(t))}),[e.networks]),(0,c.createElement)(c.Fragment,null,t&&e.networks&&(0,c.createElement)(l.F,{config:t},(0,c.createElement)(i.aH,{client:_},(0,c.createElement)(o.bO,{options:{hideBalance:!0,hideQuestionMarkCTA:!0},mode:"auto"},(0,c.createElement)(w,{currentchain:e,config:t,switchModal:a,switchHandler:m})))))}catch(e){console.log(e)}};var E=n(79896),k=n(9043);function y(){const{enabledCurrency:e,const_msg:t,currency_lbl:n,decimalchainId:a,active_network:s,total_price:r}=connect_wallts,[l,o]=(0,c.useState)(null),[m,i]=(0,c.useState)(null),[u,d]=(0,c.useState)(null),[w,_]=(0,c.useState)(!1),[y,f]=(0,c.useState)(!1),g=document.querySelector('input[name="payment_method"]:checked')?.value,N=document.querySelector("button#place_order");(0,c.useEffect)((()=>{r&&v(Number(r))}),[r]),(0,c.useEffect)((()=>{N.disabled="cpmw"===g}),[g]),(0,c.useEffect)((()=>{b(e)}),[e]);const v=async e=>{try{const t=await(0,k.Xz)(e,connect_wallts);b(t)}catch(e){console.error("Error fetching data:",e)}},b=async e=>{let t=[];0!==e.length&&(Object.values(e).forEach(((e,n)=>{if(!e.price)return;const a={value:e.symbol,label:(0,c.createElement)("span",{key:`cpmw_logos_${n}`,className:"cpmw_logos"},(0,c.createElement)("img",{key:`cpmw_logo_${n}`,src:e.url,alt:e.symbol,style:{width:"auto",height:"28px"}})," ",e.price," ",e.symbol),rating:e.price};t.push(a)})),d(t))};return(0,c.createElement)("div",{key:"show_currency_wrapper"},u?(0,c.createElement)(c.Fragment,null,(0,c.createElement)("div",{key:n,className:"cpmwp_currency_lbl"},n),(0,c.createElement)(E.ZP,{name:"cpmwp_crypto_coin",value:m,onChange:async e=>{_(!0),i(e);const t=await(0,p.M7)(a),n=await(0,k.TU)(e.value,connect_wallts);f(n),o(t),_(!1)},options:u,placeholder:t.select_cryptocurrency}),(0,c.createElement)("input",{key:s,type:"hidden",name:"cpmw_payment_network",value:s}),w&&(0,c.createElement)(p.aN,{loader:1,width:250}),!w&&l&&(0,c.createElement)(h,{networks:l,currentprice:m,networkResponse:y})):(0,c.createElement)(p.aN,{loader:1,width:250}))}}}]);