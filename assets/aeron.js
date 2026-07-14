/* AERON Solutions · scripts compartilhados */
(function(){
  const nav=document.getElementById('nav');
  if(nav) addEventListener('scroll',()=>nav.classList.toggle('scrolled',scrollY>24),{passive:true});
  const mob=document.getElementById('mobmenu');
  if(mob) mob.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>mob.classList.remove('open')));

  /* grade esférica decorativa (subhero) */
  function grid(w,h,lats,lons,latSpan,latBow,lonBow){
    const cx=w/2,cy=h/2;let d='';
    for(let j=-lats;j<=lats;j++){const t=j/lats;const y=cy+t*h*latSpan;const bow=t*h*latBow;d+=`M0 ${y.toFixed(1)} Q ${cx} ${(y+bow).toFixed(1)} ${w} ${y.toFixed(1)} `;}
    for(let i=0;i<=lons;i++){const x=(i/lons)*w;const bow=(x-cx)*lonBow;d+=`M${x.toFixed(1)} 0 Q ${(x+bow).toFixed(1)} ${cy} ${x.toFixed(1)} ${h} `;}
    return d;
  }
  document.querySelectorAll('.gridbg').forEach(el=>{
    el.innerHTML='<svg viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid slice"><path d="'+grid(1600,1000,7,20,0.46,0.16,0.21)+'"/></svg>';
  });

  /* reveal */
  const io=new IntersectionObserver(es=>es.forEach(en=>{if(en.isIntersecting){en.target.classList.add('in');io.unobserve(en.target);}}),{threshold:.16});
  document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

  /* faq */
  window.tog=function(btn){
    const qa=btn.parentElement,ans=qa.querySelector('.ans'),open=qa.classList.contains('open');
    document.querySelectorAll('.qa.open').forEach(o=>{o.classList.remove('open');o.querySelector('.ans').style.maxHeight=null;o.querySelector('button').setAttribute('aria-expanded','false');});
    if(!open){qa.classList.add('open');ans.style.maxHeight=ans.scrollHeight+'px';btn.setAttribute('aria-expanded','true');}
  };

  /* tour ao vivo (lazy no scroll): data-m = interativo, data-src = embed do Google */
  document.querySelectorAll('.live-frame[data-m],.live-frame[data-src]').forEach(lf=>{
    new IntersectionObserver((es,ob)=>{es.forEach(en=>{if(en.isIntersecting){
      const f=document.createElement('iframe');
      f.src=lf.dataset.m ? 'https://my.matterport.com/show/?m='+lf.dataset.m+'&play=1&qs=1&title=0&brand=0' : lf.dataset.src;
      f.title=lf.dataset.title||'Tour virtual navegável em 360°';f.loading='lazy';f.allowFullscreen=true;
      f.setAttribute('allow','xr-spatial-tracking; gyroscope; accelerometer; fullscreen');
      lf.appendChild(f);const l=lf.querySelector('.lz');if(l)setTimeout(()=>l.remove(),1200);ob.disconnect();
    }});},{rootMargin:'300px'}).observe(lf);
  });

  /* portfólio dinâmico (home com abas ou página de serviço com data-cat fixo) */
  const grid_=document.getElementById('pfGrid');
  if(grid_){
    const catName={tour:'Tour Virtual',google:'Google',landing:'Landing Pages'};
    const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    // miniatura: usa a imagem se houver; senao, screenshot automatico do link (ideal p/ landing pages)
    const thumb=i=>i.image ? i.image : (i.link ? 'https://s.wordpress.com/mshots/v1/'+encodeURIComponent(i.link)+'?w=1000' : '');
    const fixed=grid_.dataset.cat||'';
    const emptyMsg=grid_.dataset.empty||'Em breve, novos trabalhos por aqui.';
    const CAP=parseInt(grid_.dataset.cap||'8',10);
    let items=[],filter=fixed||'all',expanded=false;
    function card(i){return `<a class="pf-card" href="${esc(i.link)}" target="_blank" rel="noopener"><div class="im"><img src="${esc(thumb(i))}" alt="${esc(i.title)}" loading="lazy" onerror="this.style.opacity=.25"></div><div class="bd"><h3>${esc(i.title)}</h3><span class="cat ${esc(i.category)}">${catName[i.category]||esc(i.category)}</span></div></a>`;}
    function render(){
      const list=items.filter(i=>i.active!==false).sort((a,b)=>(a.order??0)-(b.order??0)).filter(i=>filter==='all'||i.category===filter);
      if(!list.length){grid_.innerHTML='<div class="pf-empty">'+emptyMsg+'</div>';return;}
      const shown=expanded?list:list.slice(0,CAP);
      grid_.innerHTML=shown.map(card).join('') + (list.length>CAP&&!expanded ? `<div class="pf-more"><button>Ver mais (${list.length-CAP})</button></div>` : '');
      const mb=grid_.querySelector('.pf-more button'); if(mb) mb.onclick=()=>{expanded=true;render();};
    }
    fetch('portfolio.json').then(r=>r.json()).then(d=>{items=d.items||[];render();}).catch(()=>{grid_.innerHTML='<div class="pf-empty">Não foi possível carregar o portfólio agora.</div>';});
    document.querySelectorAll('.pf-tab').forEach(t=>t.onclick=()=>{document.querySelectorAll('.pf-tab').forEach(x=>x.classList.remove('on'));t.classList.add('on');filter=t.dataset.cat;expanded=false;render();});
  }
})();
