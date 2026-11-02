#!/usr/bin/env python3
"""Vashasetu accessibility (WCAG, with axe-core), front-end speed and device (screen size) tests.

    npm install axe-core          (once, in the tests folder or set AXE_JS to axe.min.js)
    BASE=http://localhost/vashasetu python tests/a11y_perf_tests.py
Writes tests/results/a11y_perf_results.json and screenshots in tests/results/screens/devices/.
"""
import json, os, sys, time, uuid
import requests
from playwright.sync_api import sync_playwright

BASE = os.environ.get('BASE', 'http://127.0.0.1/vashasetu').rstrip('/') + '/'
HERE = os.path.dirname(os.path.abspath(__file__))
AXE = os.environ.get('AXE_JS') or os.path.join(HERE, 'node_modules', 'axe-core', 'axe.min.js')
SHOTS = os.path.join(HERE, 'results', 'screens', 'devices'); os.makedirs(SHOTS, exist_ok=True)
RUN = uuid.uuid4().hex[:6]
results, metrics = [], {}
def case(tid, module, title, inp, expected, actual, ok):
    res = 'Not run' if ok is None else ('Pass' if ok else 'Fail')
    results.append({'id': tid, 'module': module, 'title': title, 'input': inp, 'expected': expected, 'actual': str(actual)[:260], 'result': res})
    print({'Pass': 'PASS ', 'Fail': 'FAIL ', 'Not run': 'SKIP '}[res] + tid + ' ' + title + '   ' + str(actual)[:220])

axe_src = open(AXE, encoding='utf-8').read() if os.path.exists(AXE) else None
with sync_playwright() as pw:
    br = pw.chromium.launch()
    # ---------------------------------------------------------------- accessibility (axe-core, WCAG 2.1 A + AA)
    ctx = br.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, has_touch=True)
    pg = ctx.new_page(); pg.goto(BASE, wait_until='networkidle')
    em = f'a11y_{RUN}@example.com'
    pg.evaluate(f"fetch('api/auth.php?action=register',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{name:'A11y',email:'{em}',password:'Passw0rd!xyz'}})}})")
    def audit(name, prep):
        prep(); pg.wait_for_timeout(900)
        if not axe_src: return None
        pg.add_script_tag(content=axe_src)
        r = pg.evaluate("axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']}, resultTypes: ['violations']}).then(r => r.violations.map(v => ({id: v.id, impact: v.impact, n: v.nodes.length, help: v.help, target: v.nodes.slice(0,2).map(n => n.target.join(' '))})))")
        return r
    screens = [('splash', lambda: (pg.goto(BASE, wait_until='networkidle'))),
               ('home', lambda: pg.evaluate("goTo('home')")),
               ('translate', lambda: (pg.evaluate("goTo('translate')"), pg.fill('#dialect-input', 'আমি ভাত খাবো'), pg.click('#translate-btn'), pg.wait_for_timeout(1200))),
               ('dictionary', lambda: pg.evaluate("goTo('dictionary')")), ('history', lambda: pg.evaluate("goTo('history')")),
               ('profile', lambda: pg.evaluate("goTo('profile')")), ('quiz', lambda: pg.evaluate("goTo('quiz')"))]
    found = {}
    for name, prep in screens:
        v = audit(name, prep)
        if v is None: break
        found[name] = v
    # login/register screens as a guest
    pg2 = br.new_page(viewport={'width': 390, 'height': 844}); pg2.goto(BASE + '#login', wait_until='networkidle'); pg2.wait_for_timeout(600)
    if axe_src:
        for name in ('login', 'register'):
            pg2.evaluate(f"goTo('{name}')"); pg2.wait_for_timeout(500); pg2.add_script_tag(content=axe_src)
            found[name] = pg2.evaluate("axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']}, resultTypes: ['violations']}).then(r => r.violations.map(v => ({id: v.id, impact: v.impact, n: v.nodes.length, help: v.help, target: v.nodes.slice(0,2).map(n => n.target.join(' '))})))")
    lp = ctx.new_page(); lp.goto(BASE + 'learn.php?embedded=1', wait_until='networkidle'); lp.wait_for_timeout(1200)
    if axe_src:
        lp.add_script_tag(content=axe_src)
        found['learn'] = lp.evaluate("axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']}, resultTypes: ['violations']}).then(r => r.violations.map(v => ({id: v.id, impact: v.impact, n: v.nodes.length, help: v.help, target: v.nodes.slice(0,2).map(n => n.target.join(' '))})))")
    metrics['axe'] = found
    if axe_src:
        serious = {s: [f"{v['id']} ({v['impact']}, {v['n']})" for v in vs if v['impact'] in ('critical', 'serious')] for s, vs in found.items()}
        serious = {k: v for k, v in serious.items() if v}
        minor = {s: [f"{v['id']} ({v['impact']}, {v['n']})" for v in vs if v['impact'] not in ('critical', 'serious')] for s, vs in found.items()}
        minor = {k: v for k, v in minor.items() if v}
        case('AX-01', 'Accessibility', f'WCAG 2.1 AA check (axe-core) on {len(found)} screens: no critical or serious problems', ', '.join(found), 'No critical/serious violations', serious or 'none', not serious)
        case('AX-02', 'Accessibility', 'WCAG 2.1 AA check: moderate and minor problems', ', '.join(found), 'None', minor or 'none', not minor)
    else:
        case('AX-01', 'Accessibility', 'WCAG 2.1 AA check (axe-core)', 'needs npm install axe-core', '-', 'axe-core not installed', None)
    # keyboard
    k = br.new_page(viewport={'width': 1280, 'height': 800}); k.goto(BASE + '#translate', wait_until='networkidle'); k.wait_for_timeout(800)
    seen = []
    for _ in range(25):
        k.keyboard.press('Tab'); el = k.evaluate("(()=>{const e=document.activeElement;const r=e.getBoundingClientRect();const s=getComputedStyle(e);return [e.tagName, e.id||e.getAttribute('aria-label')||e.textContent.trim().slice(0,20), s.outlineStyle!=='none'||s.boxShadow!=='none', r.width>0]})()")
        seen.append(el)
    reach = {x[1] for x in seen}
    vis = sum(1 for x in seen if x[2])
    case('AX-03', 'Accessibility', 'The translator can be used with the keyboard only (Tab reaches the main controls, focus is visible)', '25 × Tab on the Translate screen', 'Reaches the text box, mic, Translate; focus ring shown', f'{len(reach)} different controls; focus visible on {vis}/25; text box reached: {"dialect-input" in reach}', 'dialect-input' in reach and any('Translate' in str(x[1]) or x[1] == 'translate-btn' for x in seen) and vis >= 20)
    # zoom 200 %
    z = br.new_page(viewport={'width': 640, 'height': 800}, device_scale_factor=1); z.goto(BASE + '#translate', wait_until='networkidle'); z.wait_for_timeout(600)
    z.evaluate("document.body.style.zoom='2'"); z.wait_for_timeout(400)
    over = z.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth")
    case('AX-04', 'Accessibility', 'Text zoomed to 200 % still fits (no sideways scrolling)', '640 px window, zoom 200 %', 'No horizontal overflow', f'{over} px', over <= 1)
    # ---------------------------------------------------------------- front-end speed
    def vitals(page):
        return page.evaluate("""() => new Promise(res => { const out = {};
          new PerformanceObserver(l => { for (const e of l.getEntries()) out.lcp = e.startTime; }).observe({type: 'largest-contentful-paint', buffered: true});
          let cls = 0; new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) cls += e.value; out.cls = cls; }).observe({type: 'layout-shift', buffered: true});
          setTimeout(() => { const n = performance.getEntriesByType('navigation')[0]; const p = performance.getEntriesByName('first-contentful-paint')[0] || performance.getEntriesByName('first-paint')[0];   // headless Chrome sometimes reports only first-paint
            out.fcp = p ? p.startTime : null; out.dcl = n.domContentLoadedEventEnd; out.load = n.loadEventEnd;
            const rs = performance.getEntriesByType('resource'); out.requests = rs.length + 1; out.kb = Math.round((rs.reduce((a, r) => a + (r.transferSize || 0), 0) + (n.transferSize || 0)) / 1024);
            out.cls = cls; res(out); }, 2500); })""")
    for _try in range(3):     # a first visit can be reloaded once by the new offline helper; measure again then
        c1 = br.new_context(viewport={'width': 390, 'height': 844}); p1 = c1.new_page(); p1.goto(BASE, wait_until='load'); v1 = vitals(p1)
        if v1.get('fcp') is not None: break
    metrics['fast'] = v1
    case('PF-05', 'Front-end speed', 'First visit on a fast connection: page shows quickly', 'fresh browser, local network', 'First paint < 1.8 s, largest paint < 2.5 s, layout shift < 0.1 (Google "good")', f"FCP {v1['fcp'] or 0:.0f} ms, LCP {v1.get('lcp', 0):.0f} ms, CLS {v1['cls']:.3f}, {v1['requests']} files, {v1['kb']} KB", (v1['fcp'] or 9e9) < 1800 and (v1.get('lcp') or v1['fcp'] or 9e9) < 2500 and v1['cls'] < 0.1)
    c2 = br.new_context(viewport={'width': 390, 'height': 844}); p2 = c2.new_page()
    cdp = c2.new_cdp_session(p2); cdp.send('Network.enable')
    cdp.send('Network.emulateNetworkConditions', {'offline': False, 'latency': 150, 'downloadThroughput': 1.6 * 1024 * 1024 / 8, 'uploadThroughput': 750 * 1024 / 8})
    cdp.send('Emulation.setCPUThrottlingRate', {'rate': 4})
    t0 = time.time(); p2.goto(BASE, wait_until='load', timeout=120000); v2 = vitals(p2); v2['wall_s'] = round(time.time() - t0, 1)
    if v2.get('fcp') is None: p2.goto(BASE, wait_until='load', timeout=120000); v2 = vitals(p2)
    metrics['slow4g'] = v2
    case('PF-06', 'Front-end speed', 'First visit on a slow phone network (1.6 Mbit/s, 150 ms, slow CPU ×4)', 'throttled first visit', 'Usable (first paint) within 4 s', f"FCP {v2['fcp'] or 0:.0f} ms, LCP {v2.get('lcp', 0):.0f} ms, fully loaded {v2['load']:.0f} ms, {v2['kb']} KB", (v2['fcp'] or 9e9) < 4000)
    p2.reload(wait_until='load'); v3 = vitals(p2)
    metrics['slow4g_repeat'] = v3
    case('PF-07', 'Front-end speed', 'Second visit on the slow network is much faster (files kept by the offline helper)', 'reload on the same slow network', 'Less than half the data of the first visit', f"{v3['kb']} KB downloaded (first visit {v2['kb']} KB), FCP {v3['fcp'] or 0:.0f} ms", v3['kb'] < v2['kb'] / 2)
    big = sorted([(r['name'].split('/')[-1].split('?')[0], round(r['size'] / 1024)) for r in p1.evaluate("performance.getEntriesByType('resource').map(r=>({name:r.name,size:r.encodedBodySize||0}))")], key=lambda x: -x[1])[:5]
    metrics['largest_files'] = big
    p1.evaluate("goTo('translate')"); p1.wait_for_timeout(1500)
    tt = []
    for k, txt in enumerate(['আমি ভাত খাবো', 'তুমি কোথায় যাবে', 'আমার কাছে টাকা নেই']):
        p1.fill('#dialect-input', txt); p1.evaluate("document.querySelector('#result-card').classList.remove('show')")
        t0 = time.time(); p1.click('#translate-btn'); p1.wait_for_selector('#result-card.show'); tt.append((time.time() - t0) * 1000); p1.wait_for_timeout(1200)
    case('PF-08', 'Front-end speed', 'Time from pressing Translate to seeing the result', '3 sentences, button pressed', 'Under 1 s each', ', '.join(f'{x:.0f} ms' for x in tt), max(tt) < 1000)
    # ---------------------------------------------------------------- devices / screen sizes
    devs = [('Small phone 320 px (iPhone SE 1st gen)', 320, 568, True), ('Android phone 360 px', 360, 740, True), ('Large phone 430 px', 430, 932, True),
            ('Tablet 768 px', 768, 1024, True), ('Laptop 1366 px', 1366, 768, False), ('Landscape phone 740 px', 740, 360, True)]
    bad = {}
    for name, w, h, mob in devs:
        c = br.new_context(viewport={'width': w, 'height': h}, is_mobile=mob, has_touch=mob); p = c.new_page(); p.goto(BASE + '#translate', wait_until='networkidle'); p.wait_for_timeout(700)
        p.fill('#dialect-input', 'আমি ভাত খাবো'); p.click('#translate-btn'); p.wait_for_timeout(1200)
        over = p.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth")
        nav = p.evaluate("(()=>{const n=document.querySelector('#nav');const r=n.getBoundingClientRect();return r.bottom<=innerHeight+1&&r.top<innerHeight})()")
        btn = p.evaluate("(()=>{const b=document.querySelector('#translate-btn').getBoundingClientRect();return b.width>40&&b.height>=36})()")
        p.screenshot(path=os.path.join(SHOTS, f'{w}x{h}.png'))
        if over > 1 or not nav or not btn: bad[name] = {'overflow': over, 'nav_visible': nav, 'button_ok': btn}
        c.close()
    case('DV-01', 'Devices', 'Works on 6 screen sizes from a 320 px phone to a 1366 px laptop, and in landscape', ', '.join(d[0] for d in devs), 'No sideways scroll, menu visible, Translate button usable', bad or 'all fine', not bad)
    c = br.new_context(viewport={'width': 390, 'height': 844}, color_scheme='dark'); p = c.new_page(); p.goto(BASE + '#translate', wait_until='networkidle'); p.wait_for_timeout(700)
    ratio = p.evaluate("""(()=>{const lum=c=>{const m=c.match(/[\\d.]+/g).map(Number);const f=v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)};return .2126*f(m[0])+.7152*f(m[1])+.0722*f(m[2])};
      const el=document.querySelector('#dialect-input');let e=el,bg='rgb(255,255,255)';while(e){const b=getComputedStyle(e).backgroundColor;const a=(b.match(/[\\d.]+/g)||[]).map(Number);if(a.length===3||(a.length===4&&a[3]>0.5)){bg=b;break}e=e.parentElement}
      const L1=lum(getComputedStyle(el).color),L2=lum(bg);return ((Math.max(L1,L2)+.05)/(Math.min(L1,L2)+.05)).toFixed(1)})()""")
    p.screenshot(path=os.path.join(SHOTS, 'dark_mode.png'))
    case('DV-02', 'Devices', 'Phone set to dark mode: text stays readable', 'prefers-color-scheme: dark', 'Text contrast at least 4.5 : 1', f'contrast {ratio} : 1', float(ratio) >= 4.5)
    c = br.new_context(viewport={'width': 390, 'height': 844}, reduced_motion='reduce'); p = c.new_page(); p.goto(BASE, wait_until='networkidle'); p.wait_for_timeout(500)
    anim = p.evaluate("[...document.querySelectorAll('*')].filter(e=>{const s=getComputedStyle(e);return s.animationName!=='none'&&parseFloat(s.animationDuration)>0.01&&s.animationIterationCount==='infinite'}).length")
    case('DV-03', 'Devices', 'Phone set to "reduce motion": endless animations stop', 'prefers-reduced-motion: reduce', 'No endless animations', f'{anim} endless animations', anim == 0)
    br.close()

os.makedirs(os.path.join(HERE, 'results'), exist_ok=True)
json.dump({'base': BASE, 'time': time.strftime('%Y-%m-%d %H:%M'), 'results': results, 'metrics': metrics}, open(os.path.join(HERE, 'results', 'a11y_perf_results.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
n = sum(r['result'] == 'Pass' for r in results); sk = sum(r['result'] == 'Not run' for r in results)
print(f'{n}/{len(results) - sk} accessibility/speed/device tests passed' + (f' ({sk} not run)' if sk else ''))
sys.exit(0 if n + sk == len(results) else 1)
