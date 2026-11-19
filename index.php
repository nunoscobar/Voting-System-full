<?php
require __DIR__ . '/auth.php';
$u = require_login();                 // sem sessão válida => redireciona para login.php
$isAdmin = $u['papel'] === 'admin';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title>Votação – Candidatos</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--pri:#9155fd;--pri-soft:#f0e7ff;--ok:#56ca00;--warn:#ffb400;--info:#16b1ff;--err:#ff4c51;--txt:#4d4a58;--mut:#8f8b9c;--bg:#f4f5fa}
body{font-family:'Public Sans',sans-serif;color:var(--txt);min-height:100vh;padding:2rem;
 background:linear-gradient(135deg,#7c4dff 0%,#a14bd6 45%,#e0457b 100%)}
.shell{background:var(--bg);border-radius:1rem;min-height:calc(100vh - 4rem);overflow:hidden;box-shadow:0 20px 60px rgba(40,10,90,.35)}
.side{background:#fff;width:250px;flex:none;padding:1.25rem 1rem}
.brand{display:flex;align-items:center;gap:.6rem;font-weight:700;font-size:1.3rem;letter-spacing:.5px;color:#3d3a4a;margin-bottom:1.5rem;padding-left:.5rem}
.brand i{color:var(--pri);font-size:1.1rem}
.nav-link-x{display:flex;align-items:center;gap:.75rem;padding:.65rem 1rem;border-radius:0 2rem 2rem 0;margin-right:-1rem;color:var(--txt);cursor:pointer;font-weight:500;margin-bottom:.35rem;user-select:none}
.nav-link-x:hover{background:var(--bg)}
.nav-link-x.active{background:linear-gradient(72deg,#9155fd,#c5a5fe);color:#fff;box-shadow:0 2px 8px rgba(145,85,253,.5);border-radius:.5rem;margin-right:0}
.side small{display:block;color:var(--mut);margin:1.25rem 0 .5rem .5rem;font-size:.75rem}
.main{flex:1;min-width:0;padding:1.5rem}
.topbar{background:#fff;border-radius:.6rem;padding:.75rem 1.25rem;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.cardx{background:#fff;border-radius:.75rem;box-shadow:0 2px 10px rgba(58,53,65,.1);padding:1.25rem;height:100%}
.cardx h5{font-size:1rem;font-weight:600;margin-bottom:.15rem}
.btn-pri{background:var(--pri);color:#fff;border:0;font-weight:600;box-shadow:0 2px 6px rgba(145,85,253,.5)}
.btn-pri:hover{background:#804bdf;color:#fff}
.cand-photo{width:96px;height:96px;border-radius:50%;object-fit:cover;border:4px solid var(--pri-soft)}
.cand{text-align:center;position:relative}
.cand .votes{position:absolute;top:.9rem;right:.9rem;background:var(--pri-soft);color:var(--pri);font-weight:600;font-size:.78rem;padding:.15rem .6rem;border-radius:2rem}
.cand .role{color:var(--mut);font-size:.88rem}
.vote-btn{transition:transform .08s}.vote-btn:active{transform:scale(.95)}
.stat{display:flex;align-items:center;gap:.9rem}
.stat .ic{width:44px;height:44px;border-radius:.5rem;display:grid;place-items:center;font-size:1.3rem}
.stat b{font-size:1.35rem;display:block;line-height:1.1}
.stat span{color:var(--mut);font-size:.85rem}
.chart-box{position:relative;height:600px}
.votes-input{font-weight:700;font-size:1.1rem}
.thumb{width:40px;height:40px;border-radius:50%;object-fit:cover}
.view{display:none}.view.active{display:block}
.toast-wrap{position:fixed;bottom:1.5rem;right:1.5rem;z-index:2000}
@media(max-width:991px){body{padding:.6rem}.shell{flex-direction:column}.side{width:100%}.nav-wrap{display:flex;gap:.5rem;flex-wrap:wrap}.nav-link-x{margin:0;border-radius:.5rem}.side small{display:none}}
</style>
</head>
<body data-admin="<?= $isAdmin ? '1' : '0' ?>">
<div class="shell d-flex">
 <aside class="side">
  <div class="brand">
   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="images/comissaologo.png" alt="sonair" width="50%"></div>
  <div class="brand"><i class="bi bi-check2-square"></i> CPAT - VOTAÇÃO</div>
  <div class="nav-wrap">
   <small>MENU</small>
   <div class="nav-link-x active" data-view="vote"><i class="bi bi-hand-index-thumb"></i>Votar</div>
   <div class="nav-link-x" data-view="cands"><i class="bi bi-people"></i>Candidatos</div>
   <div class="nav-link-x" data-view="results"><i class="bi bi-pie-chart"></i>Resultado final</div>
   <div class="brand"><img src="images/mascotelogo.png" alt="sonair" width="100%"></div>
  </div>
 </aside>

 <main class="main">
  <div class="topbar">
   <div><strong id="pageTitle">Votar</strong> <span class="text-secondary ms-2 small" id="totalTop"></span></div>
   <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-secondary btn-sm" id="btnExport"><i class="bi bi-download me-1"></i>Exportar JSON</button>
    <button class="btn btn-outline-secondary btn-sm" id="btnImport"><i class="bi bi-upload me-1"></i>Importar JSON</button>
    <button class="btn btn-pri btn-sm px-3" id="btnFinal"><i class="bi bi-bar-chart-line me-1"></i>Resultado final</button>
   </div>
   <div class="dropdown">
    <button class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="bi bi-person-circle me-1"></i><?= e($u['nome']) ?></button>
    <ul class="dropdown-menu dropdown-menu-end">
     <li class="dropdown-header"><?= e($u['email']) ?></li>
     <?php if ($isAdmin): ?><li><a class="dropdown-item" href="users.php"><i class="bi bi-people me-2"></i>Utilizadores</a></li><?php endif; ?>
     <li><form method="post" action="logout.php"><?= csrf_field() ?><button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Terminar sessão</button></form></li>
    </ul>
   </div>
   <input type="file" id="impFile" accept=".json,application/json" hidden>
  </div>

  <!-- VOTAR -->
  <section class="view active" id="v-vote">
   <div class="row g-4" id="voteGrid"></div>
  </section>

  <!-- CANDIDATOS -->
  <section class="view" id="v-cands">
   <div class="row g-4">
    <div class="col-lg-4">
     <div class="cardx">
      <h5>Novo candidato</h5>
      <p class="text-secondary small">Preencha os dados e guarde.</p>
      <form id="candForm" novalidate>
       <div class="text-center mb-3">
        <img id="preview" class="cand-photo" alt="Pré-visualização">
       </div>
       <div class="mb-3"><label class="form-label">Fotografia</label>
        <input type="file" class="form-control" id="photo" accept="image/*"></div>
       <div class="mb-3"><label class="form-label">Nome</label>
        <input type="text" class="form-control" id="name" maxlength="60" required></div>
       <div class="mb-3"><label class="form-label">Função</label>
        <input type="text" class="form-control" id="role" maxlength="60" required></div>
       <button class="btn btn-pri w-100" type="submit"><i class="bi bi-plus-lg me-1"></i>Guardar candidato</button>
      </form>
     </div>
    </div>
    <div class="col-lg-8">
     <div class="cardx">
      <h5>Candidatos registados</h5>
      <div class="table-responsive mt-3">
       <table class="table align-middle mb-0">
        <thead><tr><th></th><th>Nome</th><th>Função</th><th class="text-end">Votos</th><th></th></tr></thead>
        <tbody id="candTable"></tbody>
       </table>
      </div>
     </div>
    </div>
   </div>
  </section>

  <!-- RESULTADOS -->
  <section class="view" id="v-results">
   <div class="row g-4 mb-4" id="stats"></div>
   <div class="row g-4">
    <div class="col-xl-8">
     <div class="cardx">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
       <h5 class="mb-0">Votos por candidato</h5>
       <select id="chartType" class="form-select form-select-sm w-auto">
        <option value="bar">Gráfico de barras</option>
        <option value="pie">Gráfico de pizza</option>
        <option value="doughnut">Gráfico doughnut</option>
       </select>
      </div>
      <div class="chart-box"><canvas id="chart"></canvas></div>
     </div>
    </div>
    <div class="col-xl-4">
     <div class="cardx">
      <h5>Classificação</h5>
      <div class="table-responsive mt-3">
       <table class="table align-middle mb-0">
        <thead><tr><th>#</th><th>Candidato</th><th class="text-end">Votos</th><th class="text-end">%</th></tr></thead>
        <tbody id="rank"></tbody>
       </table>
      </div>
      <button class="btn btn-outline-danger btn-sm mt-3" id="resetVotes"><i class="bi bi-arrow-counterclockwise me-1"></i>Zerar votos</button>
     </div>
    </div>
   </div>
  </section>
 </main>
</div>
<div class="modal fade" id="impModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
 <div class="modal-header"><h5 class="modal-title">Importar dados</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body"><p id="impInfo" class="fw-semibold"></p>
  <div class="form-check mb-2"><input class="form-check-input" type="radio" name="impMode" id="m1" value="acrescentar" checked><label class="form-check-label" for="m1">Acrescentar aos candidatos existentes</label></div>
  <div class="form-check"><input class="form-check-input" type="radio" name="impMode" id="m2" value="substituir"><label class="form-check-label" for="m2">Substituir tudo (apaga os dados atuais)</label></div></div>
 <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-pri" id="impGo">Importar</button></div>
</div></div></div>
<div class="toast-wrap"><div id="toast" class="toast align-items-center text-bg-success border-0"><div class="d-flex"><div class="toast-body" id="toastMsg"></div></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script nonce="<?= e(csp_nonce()) ?>">
$(function(){
 const ADMIN=$('body').data('admin')==1;
 $.ajaxSetup({headers:{'X-CSRF-Token':$('meta[name=csrf-token]').attr('content')}});
 const COLORS=['#FFC000','#548235','#7030A0','#ED7D31','#4472C4','#C00000','#00B0F0','#FF66CC','#7F7F7F','#00B050'];
 const colorOf=c=>COLORS[Math.max(0,cands.indexOf(c))%COLORS.length];
 let cands=[],photoData='',chart=null;
 const toast=new bootstrap.Toast('#toast',{delay:1400});

 const api=(a,d)=>$.ajax({url:'api.php?action='+a,method:'POST',contentType:'application/json',data:JSON.stringify(d||{}),dataType:'json'})
  .fail(x=>{if(x.status===401){location.href='login.php';return}say((x.responseJSON&&x.responseJSON.erro)||'Falha na ligação ao servidor.',true)});
 const reload=()=>$.getJSON('api.php?action=listar').done(r=>{cands=r.candidatos;renderAll()})
  .fail(x=>{if(x.status===401){location.href='login.php';return}say('Não foi possível carregar os dados.',true)});
 const esc=s=>String(s).replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
 const total=()=>cands.reduce((a,c)=>a+c.votes,0);
 const pct=v=>total()?(v/total()*100).toFixed(1):'0.0';
 function avatar(c){
  if(c&&c.photo)return c.photo;
  const t=c?(c.name.trim()[0]||'?').toUpperCase():'+';
  const svg=`<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200'><rect width='200' height='200' fill='#f0e7ff'/><text x='100' y='128' font-size='90' font-family='Arial' font-weight='700' text-anchor='middle' fill='#9155fd'>${t}</text></svg>`;
  return 'data:image/svg+xml;utf8,'+encodeURIComponent(svg);
 }
 function say(msg,err){$('#toast').toggleClass('text-bg-success',!err).toggleClass('text-bg-danger',!!err);$('#toastMsg').text(msg);toast.show()}

 /* Navegação */
 const titles={vote:'Votar',cands:'Candidatos',results:'Resultado final'};
 function go(v){
  $('.view').removeClass('active');$('#v-'+v).addClass('active');
  $('.nav-link-x').removeClass('active').filter(`[data-view=${v}]`).addClass('active');
  $('#pageTitle').text(titles[v]);
  if(v==='results')renderResults();
 }
 $('.nav-link-x').on('click',function(){go($(this).data('view'))});
 $('#btnFinal').on('click',()=>go('results'));

 /* Render */
 function renderAll(){renderVote();renderTable();$('#totalTop').text(total()+' votos no total');
  if($('#v-results').hasClass('active'))renderResults()}
 function renderVote(){
  if(!cands.length){$('#voteGrid').html('<div class="col-12"><div class="cardx text-center py-5"><h5>Ainda não há candidatos</h5><p class="text-secondary">Registe o primeiro candidato para começar a votação.</p>'+(ADMIN?'<button class="btn btn-pri mx-auto" data-go="cands">Registar candidato</button>':'')+'</div></div>');return}
  $('#voteGrid').html(cands.map(c=>`
   <div class="col-sm-6 col-lg-4 col-xxl-3"><div class="cardx cand" data-id="${c.id}">
    <span class="votes">${c.votes} votos</span>
    <img class="cand-photo mb-3" style="border-color:${colorOf(c)}" src="${avatar(c)}" alt="${esc(c.name)}">
    <h5>${esc(c.name)}</h5><div class="role mb-3">${esc(c.role)}</div>
    <button class="btn btn-pri w-100 vote-btn" data-id="${c.id}"><i class="bi bi-hand-thumbs-up me-1"></i>Votar</button>
    <input type="number" min="0" step="1" class="form-control text-center mt-2 votes-input" data-id="${c.id}" value="${c.votes}" ${ADMIN?'':'readonly'} aria-label="Total de votos de ${esc(c.name)}">
   </div></div>`).join(''));
 }
 function renderTable(){
  $('#candTable').html(cands.length?cands.map(c=>`<tr>
   <td><img class="thumb" src="${avatar(c)}" alt=""></td><td class="fw-semibold">${esc(c.name)}</td>
   <td>${esc(c.role)}</td><td class="text-end">${c.votes}</td>
   <td class="text-end"><button class="btn btn-sm btn-outline-danger del" data-id="${c.id}" title="Remover"><i class="bi bi-trash"></i></button></td></tr>`).join('')
   :'<tr><td colspan="5" class="text-center text-secondary py-4">Sem candidatos registados.</td></tr>');
 }
 function renderResults(){
  const sorted=[...cands].sort((a,b)=>b.votes-a.votes),t=total(),lead=sorted[0];
  const stat=(ic,bg,col,val,lab)=>`<div class="col-sm-6 col-xl-3"><div class="cardx stat"><div class="ic" style="background:${bg};color:${col}"><i class="bi ${ic}"></i></div><div><b>${val}</b><span>${lab}</span></div></div></div>`;
  $('#stats').html(
   stat('bi-check2-all','#f0e7ff','#9155fd',t,'Total de votos')+
   stat('bi-people','#e3f6ff','#16b1ff',cands.length,'Candidatos')+
   stat('bi-trophy','#fff5da','#e09b00',lead&&t?esc(lead.name):'—','Candidato líder')+
   stat('bi-percent','#e8f9d9','#3ea700',lead&&t?pct(lead.votes)+'%':'0%','Percentagem do líder'));
  $('#rank').html(sorted.map((c,i)=>`<tr><td>${i+1}</td><td>${esc(c.name)}</td><td class="text-end">${c.votes}</td><td class="text-end">${pct(c.votes)}%</td></tr>`).join('')||'<tr><td colspan="4" class="text-secondary">Sem dados.</td></tr>');
  drawChart(cands);
 }

 /* Gráfico: valores dentro das barras e fotografias por baixo dos nomes */
 const valueLabels={id:'valueLabels',afterDatasetsDraw(ch){
  const t=total();if(!t)return;const ctx=ch.ctx,type=ch.config.type;
  ctx.save();ctx.font='700 12px Public Sans';ctx.textAlign='center';
  ch.getDatasetMeta(0).data.forEach((el,i)=>{
   const v=ch.data.datasets[0].data[i];if(!v&&type!=='bar')return;
   const txt=v+' ('+(v/t*100).toFixed(1)+'%)';
   if(type==='bar'){
    const inside=(el.base-el.y)>26;
    ctx.fillStyle=inside?'#fff':'#4d4a58';ctx.fillText(txt,el.x,inside?el.y+18:el.y-8)}
   else{const p=el.tooltipPosition();ctx.fillStyle='#fff';ctx.shadowColor='rgba(0,0,0,.5)';ctx.shadowBlur=3;ctx.fillText(txt,p.x,p.y+4)}
  });ctx.restore()}};
 let imgs={};
 function wrap(ctx,text,maxW,maxLines){
  const words=String(text).split(/\s+/),lines=[];let cur='';
  words.forEach(w=>{const t=cur?cur+' '+w:w;if(ctx.measureText(t).width<=maxW||!cur)cur=t;else{lines.push(cur);cur=w}});
  if(cur)lines.push(cur);
  if(lines.length>maxLines){lines.length=maxLines;let l=lines[maxLines-1];while(l.length>1&&ctx.measureText(l+'…').width>maxW)l=l.slice(0,-1);lines[maxLines-1]=l+'…'}
  return lines;}
 const photoPlugin={id:'photoLabels',afterDraw(ch){
  if(ch.config.type!=='bar')return;
  const x=ch.scales.x,ctx=ch.ctx,slot=x.width/Math.max(cands.length,1),maxW=slot-12;
  const w=Math.min(104,slot-16),h=w*1.3,y0=x.bottom+10,photoTop=y0+2*15+2*13+10;
  cands.forEach((c,i)=>{
   const cx=x.getPixelForValue(i);
   ctx.save();ctx.textAlign='center';ctx.textBaseline='top';
   ctx.font='700 12px Public Sans';ctx.fillStyle='#4d4a58';
   wrap(ctx,c.name.toUpperCase(),maxW,2).forEach((l,k)=>ctx.fillText(l,cx,y0+k*15));
   ctx.font='400 10.5px Public Sans';ctx.fillStyle='#8f8b9c';
   wrap(ctx,c.role,maxW,2).forEach((l,k)=>ctx.fillText(l,cx,y0+30+k*13));
   ctx.restore();
   const im=imgs[c.id];if(!im||!im.complete)return;
   ctx.save();ctx.beginPath();ctx.roundRect(cx-w/2,photoTop,w,h,6);ctx.clip();
   const r=Math.max(w/im.width,h/im.height),dw=im.width*r,dh=im.height*r;
   ctx.drawImage(im,cx-dw/2,photoTop+(h-dh)/2,dw,dh);ctx.restore();
   ctx.save();ctx.strokeStyle=colorOf(c);ctx.lineWidth=3;ctx.beginPath();ctx.roundRect(cx-w/2,photoTop,w,h,6);ctx.stroke();ctx.restore();
  });}};
 function drawChart(list){
  const type=$('#chartType').val(),bar=type==='bar';
  if(chart)chart.destroy();
  imgs={};
  list.forEach(c=>{const im=new Image();im.onload=()=>chart&&chart.draw();im.src=avatar(c);imgs[c.id]=im});
  chart=new Chart($('#chart'),{type,plugins:[valueLabels,photoPlugin],
   data:{labels:list.map(c=>c.name),datasets:[{label:'Votos',data:list.map(c=>c.votes),
    backgroundColor:list.map(colorOf),borderWidth:bar?0:2,borderColor:'#fff',borderRadius:0,maxBarThickness:130,categoryPercentage:.9,barPercentage:.72}]},
   options:{responsive:true,maintainAspectRatio:false,layout:{padding:{top:22,bottom:bar?240:0}},
    plugins:{legend:{display:!bar,position:'bottom'},
     tooltip:{callbacks:{label:c=>` ${c.parsed.y??c.parsed} votos (${pct(c.raw)}%)`}}},
    scales:bar?{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#eee'}},
     x:{grid:{display:false},ticks:{display:false}}}:{}}});
 }
 $('#chartType').on('change',renderResults);

 /* Atualiza apenas o que mudou (a caixa de texto mantém-se no lugar) */
 function refresh(c){
  $(`.votes-input[data-id=${c.id}]`).val(c.votes);
  $(`.cand[data-id=${c.id}] .votes`).text(c.votes+' votos');
  renderTable();$('#totalTop').text(total()+' votos no total');
  if($('#v-results').hasClass('active'))renderResults();
 }
 $('#voteGrid').on('change','.votes-input',function(){
  const c=cands.find(x=>x.id==$(this).data('id'));if(!c)return;
  const v=Math.max(0,parseInt($(this).val(),10)||0);
  api('definir',{id:c.id,votos:v}).done(r=>{c.votes=r.votos;refresh(c)}).fail(()=>refresh(c));
 });
 /* Votar: cada clique = 1 voto */
 $('#voteGrid').on('click','.vote-btn',function(){
  const c=cands.find(x=>x.id==$(this).data('id'));if(!c)return;
  api('votar',{id:c.id}).done(r=>{c.votes=r.votos;refresh(c);say('Voto registado para '+c.name)});
 }).on('click','[data-go]',function(){go($(this).data('go'))});
 $(document).on('click','[data-go]',function(){go($(this).data('go'))});

 /* Candidatos */
 $('#photo').on('change',function(){
  const f=this.files[0];if(!f)return;
  const r=new FileReader();
  r.onload=e=>{const img=new Image();img.onload=()=>{
    const s=300,cv=document.createElement('canvas');cv.width=cv.height=s;
    const m=Math.min(img.width,img.height),sx=(img.width-m)/2,sy=(img.height-m)/2;
    cv.getContext('2d').drawImage(img,sx,sy,m,m,0,0,s,s);
    photoData=cv.toDataURL('image/jpeg',.82);$('#preview').attr('src',photoData)};
   img.src=e.target.result};
  r.readAsDataURL(f);
 });
 $('#candForm').on('submit',function(e){
  e.preventDefault();
  const name=$('#name').val().trim(),role=$('#role').val().trim();
  $('#name').toggleClass('is-invalid',!name);$('#role').toggleClass('is-invalid',!role);
  if(!name||!role)return;
  const form=this;
  api('criar',{name,role,photo:photoData}).done(()=>{form.reset();photoData='';$('#preview').attr('src',avatar(null));reload();say('Candidato guardado')});
 });
 $('#candTable').on('click','.del',function(){
  const c=cands.find(x=>x.id==$(this).data('id'));
  if(c&&confirm('Remover '+c.name+' e os seus votos?'))api('remover',{id:c.id}).done(()=>{reload();say('Candidato removido')})
 });
 $('#resetVotes').on('click',function(){
  if(confirm('Zerar todos os votos?'))api('zerar').done(()=>{reload();say('Votos zerados')})
 });

 /* Exportar / Importar JSON */
 let importList=null;const impModal=new bootstrap.Modal('#impModal');
 $('#btnExport').on('click',()=>{location.href='api.php?action=exportar'});
 $('#btnImport').on('click',()=>$('#impFile').val('').trigger('click'));
 $('#impFile').on('change',function(){
  const f=this.files[0];if(!f)return;const r=new FileReader();
  r.onload=e=>{try{const d=JSON.parse(e.target.result),l=Array.isArray(d)?d:d.candidatos;
    if(!Array.isArray(l)||!l.length)throw 0;importList=l;
    $('#impInfo').text(l.length+' candidato(s) no ficheiro.');impModal.show()}
   catch(x){say('Ficheiro JSON inválido.',true)}};
  r.readAsText(f);
 });
 $('#impGo').on('click',()=>api('importar',{modo:$('input[name=impMode]:checked').val(),candidatos:importList})
  .done(r=>{impModal.hide();reload();say(r.importados+' candidato(s) importado(s)')}));

 if(!ADMIN)$('[data-view=cands],#btnExport,#btnImport,#resetVotes').remove();
 $('#preview').attr('src',avatar(null));reload();
});
</script>
</body>
</html>
