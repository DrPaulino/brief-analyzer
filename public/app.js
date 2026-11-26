const $=s=>document.querySelector(s);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
try{document.documentElement.dataset.theme=localStorage.getItem('ba_theme')||'dark'}catch{document.documentElement.dataset.theme='dark'}
const I=d=>`<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
const ICON={
instagram:I('<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".7" fill="currentColor"/>'),
facebook:I('<path d="M14 8h2.2V4.8h-2.4C11.5 4.8 10.2 6.2 10.2 8.5v2H8v3.2h2.2V20h3.3v-6.3h2.4l.5-3.2h-2.9V8.8c0-.5.2-.8.5-.8z" fill="currentColor" stroke="none"/>'),
whatsapp:I('<path d="M4 20l1.3-4A8 8 0 1 1 8.2 18.8z"/><path d="M9 9.5c.5 2 2.5 4 5 5l1.2-1.2-1.8-1-.8.7c-.8-.4-1.6-1.2-2-2l.7-.8-1-1.8z"/>'),
tiktok:I('<path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 4c.4 2.4 2 3.8 4.5 4"/>'),
search:I('<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>'),
youtube:I('<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M10 9.5v5l4.5-2.5z" fill="currentColor"/>'),
display:I('<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>'),
meta:I('<path d="M3 12c0-3 1.8-5 3.8-5C10 7 11 12 12 12s2-5 5.2-5C19.2 7 21 9 21 12s-1.8 5-3.8 5C14 17 13 12 12 12s-2 5-5.2 5C4.8 17 3 15 3 12z"/>'),
google:I('<path d="M20 12h-8M20 12a8 8 0 1 1-2.3-5.7"/>'),
other:I('<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>')};
const SV={instagram:{n:'Instagram',p:'meta'},facebook:{n:'Facebook',p:'meta'},whatsapp:{n:'WhatsApp',p:'meta'},tiktok:{n:'TikTok',p:'tiktok'},
search:{n:'Google Search',p:'google_ads'},youtube:{n:'YouTube',p:'google_ads'},display:{n:'Google Display',p:'google_ads'},
meta:{n:'Meta (a definir)',p:'meta'},google_ads:{n:'Google Ads (a definir)',p:'google_ads',i:'google'},other:{n:'Outras plataformas',p:'other'}};
const GENERIC=['meta','google_ads','other'];
const key=d=>SV[d.service]&&SV[d.service].p===d.platform&&!GENERIC.includes(d.service)?d.service:d.platform;
const chip=d=>{const k=key(d);return `<span class="chip-s g-${k}">${ICON[SV[k].i||k]}${SV[k].n}</span>`};
const svOpts=(p,sel)=>'<option value="">A definir</option>'+Object.entries(SV).filter(([k,v])=>v.p===p&&!GENERIC.includes(k)).map(([k,v])=>`<option value="${k}"${k===sel?' selected':''}>${v.n}</option>`).join('');
document.addEventListener('click',e=>{if(!e.target.closest('#th'))return;const t=document.documentElement.dataset.theme==='light'?'dark':'light';document.documentElement.dataset.theme=t;try{localStorage.setItem('ba_theme',t)}catch{}});
