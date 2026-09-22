export const AVIATION_SCENE_IDS = Object.freeze(['village', 'schiphol', 'arctic', 'volcanic']);
export const AVIATION_SCENE_STORAGE_KEY = 'weathernode.public.aviation-scene';

export function normalizeAviationScene(value) {
    return AVIATION_SCENE_IDS.includes(value) ? value : 'village';
}

export function loadAviationScene(storage) {
    try { return normalizeAviationScene(storage?.getItem(AVIATION_SCENE_STORAGE_KEY)); }
    catch { return 'village'; }
}

export function saveAviationScene(storage, value) {
    const scene = normalizeAviationScene(value);
    try { storage?.setItem(AVIATION_SCENE_STORAGE_KEY, scene); } catch { /* Session-only preference. */ }
    return scene;
}

function polygon(ctx, points, fill) {
    ctx.fillStyle = fill;
    ctx.beginPath();
    points.forEach(([x, y], index) => index ? ctx.lineTo(x, y) : ctx.moveTo(x, y));
    ctx.closePath();
    ctx.fill();
}

function litWindows(ctx, x, y, columns, rows, gapX, gapY, color = 'rgba(255,205,105,.55)') {
    ctx.fillStyle = color;
    for (let row = 0; row < rows; row++) for (let column = 0; column < columns; column++) {
        if ((row * columns + column) % 4 !== 3) ctx.fillRect(x + column * gapX, y + row * gapY, 2, 1.4);
    }
}

function runway(ctx, w, groundY, color = '#171b20') {
    polygon(ctx, [[.05*w, groundY], [.95*w, groundY], [.77*w, groundY+24], [.2*w, groundY+24]], color);
    ctx.strokeStyle = 'rgba(245,245,220,.7)';
    ctx.lineWidth = 1;
    ctx.setLineDash([10, 8]);
    ctx.beginPath(); ctx.moveTo(.19*w, groundY+12); ctx.lineTo(.82*w, groundY+12); ctx.stroke();
    ctx.setLineDash([]);
}

function aircraft(ctx, x, y, scale, rotation, colors = ['#dce5ea', '#e05a3f']) {
    ctx.save(); ctx.translate(x, y); ctx.rotate(rotation); ctx.scale(scale, scale);
    ctx.fillStyle = colors[0];
    ctx.beginPath(); ctx.moveTo(-15, 0); ctx.quadraticCurveTo(2, -3, 17, 0); ctx.quadraticCurveTo(3, 4, -15, 2); ctx.closePath(); ctx.fill();
    polygon(ctx, [[-1,0],[-9,8],[2,3],[10,2]], colors[0]);
    polygon(ctx, [[-1,0],[-7,-7],[2,-2],[9,0]], colors[0]);
    polygon(ctx, [[-12,0],[-16,-6],[-10,-2]], colors[1]);
    ctx.fillStyle = colors[1]; ctx.fillRect(2, -1, 8, 1.3);
    ctx.restore();
}

function windsock(ctx, x, y, wind, elapsed, motion) {
    ctx.strokeStyle = 'rgba(225,230,230,.65)'; ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(x,y); ctx.lineTo(x,y-18); ctx.stroke();
    const extension = Math.min(Math.max(wind, 0) / 15, 1);
    const flutter = motion ? Math.sin(elapsed * 7) * extension : 0;
    polygon(ctx, [[x,y-18],[x+12*extension,y-17+flutter],[x+11*extension,y-13+flutter],[x,y-14]], 'rgba(225,75,42,.8)');
    ctx.fillStyle = 'rgba(245,245,235,.85)'; ctx.fillRect(x+3*extension,y-17+flutter*.3,3*extension,3);
}

function drawSchiphol(ctx, w, h, options) {
    const y = h - options.groundHeight;
    ctx.fillStyle = '#20272a'; ctx.fillRect(0, y, w, options.groundHeight);
    ctx.fillStyle = '#303b36'; ctx.fillRect(0, y, w, 8);
    // Long terminal and piers.
    ctx.fillStyle = '#313b43'; ctx.fillRect(.2*w, y-19, .43*w, 18);
    ctx.fillStyle = '#46535a'; ctx.fillRect(.18*w, y-21, .47*w, 5);
    litWindows(ctx, .21*w, y-14, Math.max(4, Math.floor(w/55)), 3, 9, 4);
    ctx.strokeStyle='rgba(125,158,174,.45)'; ctx.lineWidth=.6;
    for (let x=.21*w; x<.63*w; x+=9) { ctx.beginPath(); ctx.moveTo(x,y-16); ctx.lineTo(x,y-2); ctx.stroke(); }
    for (const px of [.25,.36,.48,.58]) {
        ctx.fillStyle='#39444b'; ctx.fillRect(px*w,y-10,28,7);
        ctx.fillStyle='#65737a'; ctx.fillRect(px*w+23,y-8,13,3); // jet bridge
        ctx.fillStyle='#20282d'; ctx.fillRect(px*w+33,y-5,3,5);
    }
    // Control tower kept inside the lower band.
    ctx.fillStyle='#263038'; ctx.fillRect(.71*w,y-34,8,34);
    polygon(ctx, [[.695*w,y-37],[.742*w,y-37],[.735*w,y-30],[.703*w,y-30]], '#50616b');
    ctx.fillStyle='rgba(155,215,235,.45)'; ctx.fillRect(.705*w,y-35,.027*w,3);
    ctx.strokeStyle='rgba(205,220,225,.65)'; ctx.beginPath(); ctx.moveTo(.719*w,y-37); ctx.lineTo(.719*w,y-47); ctx.stroke();
    ctx.beginPath(); ctx.arc(.719*w,y-48,1.5,0,Math.PI*2); ctx.stroke();
    runway(ctx,w,y+5);
    // Deterministic edge lights and apron markings.
    for (let x=.1*w; x<.91*w; x+=22) {
        ctx.fillStyle=(Math.round(x/22)%2) ? '#6fb7e5' : '#d8e7d6';
        ctx.fillRect(x,y+5,1.5,1.5); ctx.fillRect(x,y+27,1.5,1.5);
    }
    ctx.strokeStyle='rgba(238,190,54,.5)'; ctx.lineWidth=.8;
    for (const px of [.27,.38,.50,.60]) { ctx.beginPath(); ctx.moveTo(px*w,y); ctx.quadraticCurveTo(px*w+14,y+7,px*w+24,y+10); ctx.stroke(); }
    const t = options.motion ? options.elapsed : 0;
    aircraft(ctx, ((.12*w + t*16) % (w*.75)) + .08*w, y+11, .68, 0);
    aircraft(ctx,.31*w,y-1,.48,0,['#e0e7e9','#3c7daa']);
    aircraft(ctx,.43*w,y-1,.48,0,['#e5e7e4','#da9734']);
    aircraft(ctx,.55*w,y-1,.48,0,['#dce5ea','#5b75b2']);
    aircraft(ctx, .82*w + (options.motion ? Math.min((t%12)/12,1)*.12*w : 0), y-24-(options.motion ? (t%12)*2 : 0), .68, -.16);
    const vanX=.66*w+(options.motion ? (t*9)%(w*.11) : 0);
    ctx.fillStyle='#e3b53e'; ctx.fillRect(vanX,y+3,11,5); ctx.fillStyle='#1d2830'; ctx.fillRect(vanX+7,y+1,4,3);
    ctx.beginPath(); ctx.arc(vanX+2,y+9,1.5,0,Math.PI*2); ctx.arc(vanX+9,y+9,1.5,0,Math.PI*2); ctx.fill();
    windsock(ctx,.12*w,y,options.wind,t,options.motion);
}

function drawArctic(ctx, w, h, options) {
    const y = h - options.groundHeight;
    // Rock and ice remain below the atmospheric scale's lowest useful band.
    polygon(ctx, [[0,y+4],[.14*w,y-29],[.23*w,y-5],[.34*w,y-37],[.48*w,y+2],[.66*w,y-24],[.8*w,y+3],[w,y-16],[w,h],[0,h]], '#263746');
    polygon(ctx, [[.08*w,y-13],[.14*w,y-29],[.2*w,y-8],[.29*w,y-22],[.34*w,y-37],[.4*w,y-9]], '#b9cfda');
    polygon(ctx, [[.14*w,y-29],[.165*w,y-18],[.19*w,y-13],[.2*w,y-8]], '#edf4f5');
    polygon(ctx, [[.29*w,y-22],[.34*w,y-37],[.365*w,y-23],[.4*w,y-9]], '#e5eff2');
    polygon(ctx, [[.61*w,y-12],[.66*w,y-24],[.71*w,y-9],[.79*w,y-18],[.86*w,y-5]], '#738e9c');
    ctx.fillStyle='#d4e3e8'; ctx.fillRect(0,y,w,options.groundHeight);
    ctx.fillStyle='rgba(126,174,193,.45)'; ctx.fillRect(0,y+7,w,3);
    runway(ctx,w,y+8,'#53636a');
    for (let x=.08*w;x<.93*w;x+=32) { ctx.fillStyle='#ef6b45'; ctx.fillRect(x,y+7,2,5); ctx.fillStyle='#f2eee2'; ctx.fillRect(x,y+7,2,2); }
    // Research station modules, antenna and fuel tanks.
    for (const [x,c] of [[.17,'#d66a42'],[.23,'#d9ddd8'],[.29,'#d6a43e']]) {
        ctx.fillStyle=c; ctx.fillRect(x*w,y-12,32,12); ctx.fillStyle='#1d3442'; ctx.fillRect(x*w+6,y-8,5,4); ctx.fillRect(x*w+20,y-8,5,4);
    }
    ctx.strokeStyle='rgba(220,235,240,.8)'; ctx.beginPath(); ctx.moveTo(.37*w,y); ctx.lineTo(.37*w,y-28); ctx.stroke();
    ctx.save(); ctx.translate(.37*w,y-29); ctx.rotate(-.35); ctx.beginPath(); ctx.arc(0,0,7,0,Math.PI); ctx.stroke(); ctx.restore();
    for (const tx of [.73,.77,.81]) { ctx.fillStyle='#aebfc5'; ctx.beginPath(); ctx.ellipse(tx*w,y-5,8,5,0,0,Math.PI*2); ctx.fill(); ctx.strokeStyle='#7d929a'; ctx.stroke(); }
    aircraft(ctx,.58*w,y+13,.63,0,['#e8eeee','#d96742']);
    // Compact tracked field vehicle.
    const crawlerX=.43*w+(options.motion ? (options.elapsed*5)%(w*.08) : 0);
    ctx.fillStyle='#e69a32'; ctx.fillRect(crawlerX,y+1,14,7); ctx.fillStyle='#243843'; ctx.fillRect(crawlerX+8,y-3,6,4);
    ctx.fillRect(crawlerX-1,y+8,17,3); ctx.strokeStyle='#b9d2da'; ctx.beginPath(); ctx.moveTo(crawlerX+14,y-2); ctx.lineTo(crawlerX+18,y-7); ctx.stroke();
    // Dark plaque lets the shared translucent Ground label remain readable on snow.
    ctx.fillStyle='rgba(25,45,55,.62)'; ctx.fillRect(0,y+2,54,18);
    windsock(ctx,.88*w,y,options.wind,options.elapsed,options.motion);
}

function drawVolcanic(ctx, w, h, options) {
    const y = h - options.groundHeight;
    ctx.fillStyle='#112b34'; ctx.fillRect(0,y,w,options.groundHeight);
    polygon(ctx, [[0,y+5],[.08*w,y-12],[.18*w,y-2],[.31*w,y-38],[.42*w,y-4],[.53*w,y-27],[.65*w,y+3],[.76*w,y-19],[.88*w,y-2],[w,y-10],[w,h],[0,h]], '#211e22');
    polygon(ctx, [[.18*w,y-2],[.31*w,y-38],[.42*w,y-4]], '#32292a');
    polygon(ctx, [[.255*w,y-24],[.31*w,y-38],[.35*w,y-25],[.31*w,y-28]], '#514041');
    ctx.fillStyle='#151719'; ctx.fillRect(0,y,w,options.groundHeight);
    ctx.fillStyle='#173943'; ctx.beginPath(); ctx.ellipse(.1*w,y+30,.23*w,22,0,0,Math.PI*2); ctx.fill();
    // Pale surf along the black coast; phase movement is decorative only.
    const waveShift=options.motion ? Math.sin(options.elapsed*1.7)*3 : 0;
    ctx.strokeStyle='rgba(159,210,216,.5)'; ctx.lineWidth=1;
    ctx.beginPath(); ctx.moveTo(0,y+13); ctx.bezierCurveTo(.05*w,y+8+waveShift,.11*w,y+18-waveShift,.19*w,y+11); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(.02*w,y+22); ctx.bezierCurveTo(.07*w,y+18-waveShift,.13*w,y+26+waveShift,.2*w,y+18); ctx.stroke();
    runway(ctx,w,y+7,'#282729');
    // Warm island terminal, hangar and beacon.
    ctx.fillStyle='#4a3027'; ctx.fillRect(.63*w,y-17,.18*w,17);
    polygon(ctx, [[.62*w,y-17],[.72*w,y-25],[.82*w,y-17]], '#2b2020');
    litWindows(ctx,.65*w,y-12,Math.max(3,Math.floor(w/85)),2,11,5,'rgba(255,181,70,.8)');
    ctx.fillStyle='#332b2a'; ctx.fillRect(.48*w,y-13,.1*w,13);
    ctx.fillStyle='#171a1c'; ctx.fillRect(.49*w,y-10,.08*w,10); ctx.strokeStyle='rgba(227,136,57,.45)';
    for (let gx=.5*w;gx<.57*w;gx+=8) { ctx.beginPath(); ctx.moveTo(gx,y-10); ctx.lineTo(gx,y); ctx.stroke(); }
    // Lighthouse on the sea cliff.
    polygon(ctx, [[.125*w,y-6],[.132*w,y-27],[.145*w,y-27],[.152*w,y-6]], '#d8d3c7');
    ctx.fillStyle='#a74639'; ctx.fillRect(.131*w,y-22,.015*w,4); ctx.fillRect(.13*w,y-29,.018*w,3);
    ctx.fillStyle='rgba(255,198,85,.75)'; ctx.fillRect(.134*w,y-28,.01*w,2);
    ctx.strokeStyle='rgba(255,190,80,.8)'; ctx.beginPath(); ctx.moveTo(.87*w,y); ctx.lineTo(.87*w,y-24); ctx.stroke();
    ctx.fillStyle=options.motion && Math.sin(options.elapsed*4)>0 ? '#ffd275' : '#e66a42'; ctx.beginPath(); ctx.arc(.87*w,y-25,2,0,Math.PI*2); ctx.fill();
    aircraft(ctx,.34*w,y+15,.66,0,['#dad5cf','#d27b3f']);
    windsock(ctx,.91*w,y,options.wind,options.elapsed,options.motion);
}

const renderers = { schiphol: drawSchiphol, arctic: drawArctic, volcanic: drawVolcanic };

export function drawAviationScene(ctx, scene, dimensions, options = {}) {
    const renderer = renderers[normalizeAviationScene(scene)];
    if (!renderer) return false;
    ctx.save();
    renderer(ctx, dimensions.width, dimensions.height, {
        groundHeight: options.groundHeight ?? 60,
        elapsed: Math.max(0, Math.min(Number(options.elapsed) || 0, 86400)),
        wind: Number(options.wind) || 0,
        motion: options.motion !== false,
    });
    ctx.restore();
    return true;
}
