<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DistroFlow | Next-Gen Logistics Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;overflow:hidden;background:#0A0F1A;color:#fff;height:100vh}
        #canvas-container{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0}
        .login-container{position:relative;z-index:10;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
        .login-card{background:rgba(15,23,42,0.65);backdrop-filter:blur(24px);border-radius:40px;padding:44px 40px;width:100%;max-width:480px;border:1px solid rgba(255,255,255,0.12);box-shadow:0 25px 50px -12px rgba(0,0,0,0.5),0 0 0 1px rgba(99,102,241,0.1);transition:transform 0.3s cubic-bezier(0.2,0.9,0.4,1.1),box-shadow 0.3s ease;animation:fadeInUp 0.8s ease-out}
        .login-card:hover{transform:translateY(-4px);box-shadow:0 35px 60px -15px rgba(0,0,0,0.6),0 0 0 1px rgba(99,102,241,0.3)}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        .logo{text-align:center;margin-bottom:32px}
        .logo-icon{width:70px;height:70px;background:linear-gradient(135deg,#6366F1,#10B981);border-radius:22px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:32px;font-weight:800;color:#fff;box-shadow:0 10px 25px -5px rgba(99,102,241,0.4);transition:transform 0.3s ease}
        .logo-icon:hover{transform:scale(1.02)}
        .logo h1{font-size:28px;font-weight:700;background:linear-gradient(135deg,#fff,#A1A1AA);-webkit-background-clip:text;background-clip:text;color:transparent;letter-spacing:-0.5px}
        .logo .tagline{font-size:13px;color:#64748B;margin-top:6px;letter-spacing:0.3px}
        .stats-preview{display:flex;justify-content:space-between;background:rgba(255,255,255,0.03);border-radius:24px;padding:18px 16px;margin-bottom:32px;border:1px solid rgba(255,255,255,0.06)}
        .stat-item{text-align:center;flex:1}
        .stat-value{font-size:22px;font-weight:700;color:#fff}
        .stat-label{font-size:10px;color:#64748B;margin-top:5px;text-transform:uppercase;letter-spacing:0.5px}
        .input-group{position:relative;margin-bottom:20px}
        .input-group i{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#64748B;font-size:18px;transition:color 0.2s;z-index:1}
        .input-group input{width:100%;padding:15px 16px 15px 48px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-radius:18px;font-size:14px;color:#fff;transition:all 0.25s;font-family:'Inter',sans-serif}
        .input-group input:focus{outline:none;border-color:#6366F1;background:rgba(255,255,255,0.08);box-shadow:0 0 0 3px rgba(99,102,241,0.15)}
        .input-group input:focus+i{color:#6366F1}
        .input-group input::placeholder{color:#475569}
        .password-toggle{position:absolute;right:16px;top:50%;transform:translateY(-50%);cursor:pointer;color:#64748B;z-index:1}
        .form-options{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;font-size:13px}
        .checkbox{display:flex;align-items:center;gap:8px;color:#94A3B8;cursor:pointer}
        .checkbox input{width:16px;height:16px;cursor:pointer;accent-color:#6366F1}
        .forgot-link{color:#6366F1;text-decoration:none;transition:color 0.2s}
        .forgot-link:hover{color:#10B981}
        .login-btn{width:100%;padding:15px;background:linear-gradient(135deg,#6366F1,#06B6D4);border:none;border-radius:18px;font-size:15px;font-weight:600;color:#fff;cursor:pointer;transition:all 0.3s;position:relative;overflow:hidden;font-family:'Inter',sans-serif}
        .login-btn:hover{transform:translateY(-2px);box-shadow:0 10px 25px -5px rgba(99,102,241,0.5)}
        .login-btn:active{transform:translateY(0)}
        .login-btn.loading{pointer-events:none;opacity:0.8}
        .login-btn.loading::after{content:'';position:absolute;width:18px;height:18px;border:2px solid transparent;border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;right:20px;top:50%;transform:translateY(-50%)}
        @keyframes spin{to{transform:translateY(-50%) rotate(360deg)}}
        .error-message{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);border-radius:16px;padding:12px 16px;margin-bottom:24px;color:#F87171;font-size:13px;text-align:center}
        .ripple{position:absolute;border-radius:50%;background:rgba(255,255,255,0.5);transform:scale(0);animation:rippleAnim 0.6s linear;pointer-events:none}
        @keyframes rippleAnim{to{transform:scale(4);opacity:0}}
        .support-link{text-align:center;margin-top:28px}
        .support-link a{color:#64748B;text-decoration:none;font-size:13px;transition:color 0.2s}
        .support-link a:hover{color:#6366F1}
        .status-bar{position:fixed;bottom:0;left:0;right:0;background:rgba(0,0,0,0.5);backdrop-filter:blur(12px);padding:10px 24px;display:flex;justify-content:space-between;align-items:center;z-index:10;font-size:12px;color:#94A3B8;border-top:1px solid rgba(255,255,255,0.05)}
        .live-indicator{display:flex;align-items:center;gap:8px}
        .live-dot{width:8px;height:8px;background:#10B981;border-radius:50%;animation:pulseDot 1.5s infinite;box-shadow:0 0 5px #10B981}
        @keyframes pulseDot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.6;transform:scale(1.2)}}
        .status-right{display:flex;gap:24px}
        .no-webgl{position:fixed;bottom:20px;left:20px;background:rgba(0,0,0,0.8);color:#F87171;padding:10px 15px;border-radius:10px;font-size:12px;z-index:100;display:none}
        @media(max-width:768px){.login-card{padding:32px 24px;margin:0 16px}.stats-preview{padding:12px}.stat-value{font-size:16px}.status-bar{flex-direction:column;gap:8px;text-align:center}.status-right{flex-wrap:wrap;justify-content:center;gap:16px}}
        @media(prefers-reduced-motion:reduce){*{animation-duration:0.01ms!important}}
    </style>
</head>
<body>
<div id="canvas-container"></div>
<div class="no-webgl" id="webgl-fallback"><i class="fas fa-exclamation-triangle"></i> WebGL not supported. Please update your browser.</div>

<div class="login-container">
    <div class="login-card" id="loginCard">
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-chart-network"></i></div>
            <h1>DistroFlow</h1>
            <div class="tagline">Next-Gen Logistics Intelligence</div>
        </div>
        <div class="stats-preview">
            <div class="stat-item"><div class="stat-value" id="previewDeliveries">0</div><div class="stat-label">Active Deliveries</div></div>
            <div class="stat-item"><div class="stat-value" id="previewDrivers">0</div><div class="stat-label">Drivers Online</div></div>
            <div class="stat-item"><div class="stat-value" id="previewOntime">0<span style="font-size:12px;">%</span></div><div class="stat-label">On-Time Rate</div></div>
        </div>
        @if($errors->any())
        <div class="error-message"><i class="fas fa-exclamation-triangle"></i> {{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('login') }}" id="loginForm">
            @csrf
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email Address" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" placeholder="Password" required>
                <i class="fas fa-eye password-toggle" id="togglePassword"></i>
            </div>
            <div class="form-options">
                <label class="checkbox"><input type="checkbox" name="remember"> Remember Me</label>
                <a href="#" class="forgot-link">Forgot Password?</a>
            </div>
            <button type="submit" class="login-btn" id="loginBtn">Sign In <i class="fas fa-arrow-right" style="margin-left:8px;"></i></button>
        </form>
        <div class="support-link"><a href="#"><i class="fas fa-headset"></i> Enterprise Support</a></div>
    </div>
</div>

<div class="status-bar">
    <div class="live-indicator">
        <span class="live-dot"></span><span>LIVE OPERATIONS</span><span>•</span><span>Global Network Active</span>
    </div>
    <div class="status-right">
        <span><i class="fas fa-map-marker-alt"></i> Cairo, Egypt</span>
        <span><i class="far fa-clock"></i> <span id="liveClock">--:--:--</span></span>
        <span><i class="far fa-calendar-alt"></i> <span id="liveDate"></span></span>
    </div>
</div>

<script type="importmap">
{
    "imports": {
        "three": "https://unpkg.com/three@0.128.0/build/three.module.js"
    }
}
</script>

<script type="module">
import * as THREE from 'three';

const container = document.getElementById('canvas-container');
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0A0F1A);
scene.fog = new THREE.FogExp2(0x0A0F1A, 0.0008);

const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
camera.position.set(0, 8, 18);
camera.lookAt(0, 0, 0);

const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setClearColor(0x0A0F1A, 1);
container.appendChild(renderer.domElement);

const ambientLight = new THREE.AmbientLight(0x404060);
scene.add(ambientLight);
const dirLight = new THREE.DirectionalLight(0xffffff, 1.2);
dirLight.position.set(2, 5, 3);
scene.add(dirLight);
const backLight = new THREE.PointLight(0x6366F1, 0.5);
backLight.position.set(-2, 1, -4);
scene.add(backLight);
const fillLight = new THREE.PointLight(0x10B981, 0.3);
fillLight.position.set(1, 2, 2);
scene.add(fillLight);

const seg = 200;
const size = 22;
const geo = new THREE.PlaneGeometry(size, size, seg, seg);
geo.rotateX(-Math.PI / 2);
const pos = geo.attributes.position.array;
const ox = [], oz = [];
for (let i = 0; i < pos.length / 3; i++) { ox.push(pos[i*3]); oz.push(pos[i*3+2]); }
const cols = [];
for (let i = 0; i <= seg; i++) {
    const t = i / seg;
    for (let j = 0; j <= seg; j++) {
        const s = j / seg;
        cols.push(0.2 + Math.sin(s*Math.PI)*0.3, 0.3 + Math.cos(t*Math.PI)*0.4 + 0.2, 0.5 + Math.sin((s+t)*Math.PI)*0.3);
    }
}
geo.setAttribute('color', new THREE.BufferAttribute(new Float32Array(cols), 3));

const mat = new THREE.MeshStandardMaterial({
    vertexColors: true, roughness: 0.4, metalness: 0.7, emissive: 0x111122,
    emissiveIntensity: 0.3, flatShading: false, side: THREE.DoubleSide
});
const wave = new THREE.Mesh(geo, mat);
scene.add(wave);

const pc = 1800;
const pg = new THREE.BufferGeometry();
const pp = new Float32Array(pc * 3);
const ps = [];
for (let i = 0; i < pc; i++) {
    pp[i*3] = (Math.random()-0.5)*40;
    pp[i*3+1] = Math.random()*6+0.5;
    pp[i*3+2] = (Math.random()-0.5)*25-5;
    ps.push({x:(Math.random()-0.5)*0.008,y:Math.random()*0.01+0.002,z:(Math.random()-0.5)*0.008});
}
pg.setAttribute('position', new THREE.BufferAttribute(pp, 3));
const ptMat = new THREE.PointsMaterial({ color: 0x6366F1, size: 0.08, transparent: true, opacity: 0.6, blending: THREE.AdditiveBlending });
const parts = new THREE.Points(pg, ptMat);
scene.add(parts);

const lp = [];
for (let i = 0; i < 80; i++) {
    lp.push({
        s: new THREE.Vector3((Math.random()-0.5)*18, Math.random()*4+1, (Math.random()-0.5)*14),
        e: new THREE.Vector3((Math.random()-0.5)*18, Math.random()*4+1, (Math.random()-0.5)*14)
    });
}
const lMat = new THREE.LineBasicMaterial({ color: 0x10B981, transparent: true, opacity: 0.25 });
const lines = [];
lp.forEach(p => { const l = new THREE.Line(new THREE.BufferGeometry().setFromPoints([p.s, p.e]), lMat); scene.add(l); lines.push(l); });

function mkTruck(c) {
    const cv = document.createElement('canvas'); cv.width=64; cv.height=64;
    const cx = cv.getContext('2d');
    cx.fillStyle=c; cx.fillRect(8,20,48,24);
    cx.fillStyle='#1E293B'; cx.fillRect(16,12,32,16);
    cx.fillStyle='#000'; cx.beginPath(); cx.arc(18,44,8,0,Math.PI*2); cx.fill();
    cx.beginPath(); cx.arc(46,44,8,0,Math.PI*2); cx.fill();
    cx.fillStyle='#FBBF24'; cx.beginPath(); cx.arc(46,44,4,0,Math.PI*2); cx.fill();
    cx.beginPath(); cx.arc(18,44,4,0,Math.PI*2); cx.fill();
    return new THREE.CanvasTexture(cv);
}
const trucks = [];
for (let i = 0; i < 12; i++) {
    const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: mkTruck('#3B82F6'), transparent: true }));
    sp.scale.set(0.8,0.8,1);
    scene.add(sp);
    trucks.push({ sp, p: Math.random(), s: 0.002+Math.random()*0.003, sx: (Math.random()-0.5)*16, sz: (Math.random()-0.5)*12, ex: (Math.random()-0.5)*16, ez: (Math.random()-0.5)*12 });
}

let t = 0, mx = 0, my = 0, mfx = 0, mfz = 0;
window.addEventListener('mousemove', e => {
    mx = e.clientX / window.innerWidth * 2 - 1;
    my = e.clientY / window.innerHeight * 2 - 1;
    mfx = mx * 3; mfz = my * 2;
});

const card = document.getElementById('loginCard');
window.addEventListener('mousemove', e => {
    const r = card.getBoundingClientRect();
    const rx = (e.clientY - r.top - r.height/2) / 25;
    const ry = (e.clientX - r.left - r.width/2) / 25;
    card.style.transform = `perspective(1000px) rotateX(${rx*0.5}deg) rotateY(${ry*0.5}deg) translateY(-4px)`;
});
card.addEventListener('mouseleave', () => { card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px)'; });

function upWave() {
    const p = geo.attributes.position.array;
    for (let i = 0; i <= seg; i++) {
        for (let j = 0; j <= seg; j++) {
            const idx = (i*(seg+1)+j)*3;
            const x = ox[idx/3], z = oz[idx/3];
            let y = Math.sin(x*0.6+t)*Math.cos(z*0.5+t*0.7)*0.25 + Math.sin(x*1.3-t*1.2)*0.15 + Math.cos(z*1.1+t*0.9)*0.15 + Math.sin((x*1.8+z*1.2)*0.8+t*1.5)*0.1;
            const dx = x - mfx, dz = z - mfz, d = Math.sqrt(dx*dx+dz*dz);
            if (d < 3.5) y -= 0.25*(1-d/3.5);
            p[idx+1] = y;
        }
    }
    geo.attributes.position.needsUpdate = true;
    geo.computeVertexNormals();
}
function upParts() {
    const p = parts.geometry.attributes.position.array;
    for (let i = 0; i < pc; i++) {
        p[i*3] += ps[i].x; p[i*3+1] += ps[i].y; p[i*3+2] += ps[i].z;
        const dx = p[i*3] - mfx, dz = p[i*3+2] - mfz, d = Math.sqrt(dx*dx+dz*dz);
        if (d < 2.5) { const a = Math.atan2(dz,dx), f = 0.03*(1-d/2.5); p[i*3] += Math.cos(a)*f; p[i*3+2] += Math.sin(a)*f; }
        if (p[i*3] > 20) p[i*3] = -20; if (p[i*3] < -20) p[i*3] = 20;
        if (p[i*3+1] > 6) p[i*3+1] = 0.5; if (p[i*3+1] < 0.2) p[i*3+1] = 5;
        if (p[i*3+2] > 15) p[i*3+2] = -15; if (p[i*3+2] < -15) p[i*3+2] = 15;
    }
    parts.geometry.attributes.position.needsUpdate = true;
}
function upTrucks() {
    const p = geo.attributes.position.array;
    for (const tr of trucks) {
        tr.p += tr.s; if (tr.p >= 1) tr.p = 0;
        const x = tr.sx + (tr.ex - tr.sx) * tr.p, z = tr.sz + (tr.ez - tr.sz) * tr.p;
        let y = 0;
        for (let v = 0; v < p.length/3; v++) { if (Math.abs(p[v*3]-x)<0.3 && Math.abs(p[v*3+2]-z)<0.3) { y = p[v*3+1]+0.2; break; } }
        tr.sp.position.set(x, y, z);
    }
}
function upLines() {
    const pl = Math.sin(t*3)*0.3+0.4;
    lines.forEach(l => { l.material.opacity = 0.2 + pl*0.2; });
}
function upCam() {
    camera.position.x += (mfx*0.03-camera.position.x)*0.05;
    camera.position.z += (mfz*0.05-camera.position.z)*0.05;
    camera.lookAt(0,0,0);
}

function clock() {
    const n = new Date(), c = new Date(n.toLocaleString('en-US',{timeZone:'Africa/Cairo'}));
    document.getElementById('liveClock').innerText = String(c.getHours()).padStart(2,'0')+':'+String(c.getMinutes()).padStart(2,'0')+':'+String(c.getSeconds()).padStart(2,'0');
    document.getElementById('liveDate').innerText = c.toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
}
function counters() {
    const tg = {deliveries:1247,drivers:18,ontime:94}, el = {deliveries:document.getElementById('previewDeliveries'),drivers:document.getElementById('previewDrivers'),ontime:document.getElementById('previewOntime')};
    Object.keys(tg).forEach(k => {
        let cur=0, tgv=tg[k], st=20, stp=1500/st, inc=tgv/stp, iv=setInterval(()=>{cur+=inc;if(cur>=tgv){el[k].innerHTML=k==='ontime'?tgv+'<span style="font-size:12px;">%</span>':Math.floor(tgv);clearInterval(iv)}else el[k].innerHTML=k==='ontime'?Math.floor(cur)+'<span style="font-size:12px;">%</span>':Math.floor(cur)},st);
    });
}

const btn=document.getElementById('loginBtn'),form=document.getElementById('loginForm');
if(btn){btn.addEventListener('click',e=>{const r=document.createElement('span');r.classList.add('ripple');const rc=btn.getBoundingClientRect(),s=Math.max(rc.width,rc.height);r.style.width=r.style.height=s+'px';r.style.left=e.clientX-rc.left-s/2+'px';r.style.top=e.clientY-rc.top-s/2+'px';btn.appendChild(r);setTimeout(()=>r.remove(),600)});if(form)form.addEventListener('submit',()=>{btn.classList.add('loading');btn.innerHTML='Signing In...'})}
const tp=document.getElementById('togglePassword'),pw=document.getElementById('password');
if(tp)tp.addEventListener('click',()=>{const ty=pw.type==='password'?'text':'password';pw.type=ty;tp.classList.toggle('fa-eye');tp.classList.toggle('fa-eye-slash')});

let lt=performance.now();
function anim(){
    const n=performance.now(), d=Math.min(1/30,(n-lt)/1000); lt=n; t+=d*1.5;
    upWave(); upParts(); upTrucks(); upLines(); upCam();
    renderer.render(scene,camera);
    requestAnimationFrame(anim);
}

window.addEventListener('resize',()=>{camera.aspect=window.innerWidth/window.innerHeight;camera.updateProjectionMatrix();renderer.setSize(window.innerWidth,window.innerHeight)});
if(!renderer.capabilities.isWebGL2) document.getElementById('webgl-fallback').style.display='block';

clock(); setInterval(clock,1000); counters(); anim();

const sg=new THREE.BufferGeometry(); const sc=800; const sp2=new Float32Array(sc*3);
for(let i=0;i<sc;i++){sp2[i*3]=(Math.random()-0.5)*200;sp2[i*3+1]=(Math.random()-0.5)*80;sp2[i*3+2]=(Math.random()-0.5)*100-50}
sg.setAttribute('position',new THREE.BufferAttribute(sp2,3));
scene.add(new THREE.Points(sg,new THREE.PointsMaterial({color:0xffffff,size:0.08,transparent:true,opacity:0.4})));
</script>
</body>
</html>