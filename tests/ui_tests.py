#!/usr/bin/env python3
"""Vashasetu browser (user-interface) tests with Playwright + Chromium.

Run against a running copy of the app:
    BASE=http://localhost/vashasetu python tests/ui_tests.py
Needs: pip install playwright requests && python -m playwright install chromium
Screens are saved in tests/results/screens/, results in tests/results/ui_results.json.
"""
import json, os, re, sys, time, uuid, unicodedata
nfc = lambda x: unicodedata.normalize('NFC', x)
import requests
from playwright.sync_api import sync_playwright

BASE = os.environ.get('BASE', 'http://127.0.0.1:8000').rstrip('/') + '/'
HERE = os.path.dirname(os.path.abspath(__file__))
SHOTS = os.path.join(HERE, 'results', 'screens'); os.makedirs(SHOTS, exist_ok=True)
RUN = uuid.uuid4().hex[:6]
PHONE = {'width': 390, 'height': 844}
results = []

def case(tid, module, title, steps, expected, actual, ok, shot=None):
    results.append({'id': tid, 'module': module, 'title': title, 'input': steps, 'expected': expected,
                    'actual': str(actual)[:220], 'result': 'Pass' if ok else 'Fail', 'screenshot': shot})
    print(('PASS ' if ok else 'FAIL ') + tid + ' ' + title + ('' if ok else '   -> got: ' + str(actual)[:200]))

def snap(page, name):
    p = os.path.join(SHOTS, name + '.png'); page.screenshot(path=p); return p

def safe(tid, module, title, steps, expected, fn):
    try: fn()
    except Exception as e: case(tid, module, title, steps, expected, 'Error: ' + str(e).split('\n')[0], False)

# Fake microphone: Chrome's speech recogniser replaced by one that "hears" a Bangla sentence.
MIC_MOCK = """
(() => {
  window.__micLog = [];
  class FakeSR {
    constructor(){ this.lang=''; this.continuous=false; this.interimResults=false; window.__micLog.push('new'); }
    start(){ window.__lastSR=this; window.__micLog.push('start:'+this.lang); setTimeout(()=>{ this.onstart&&this.onstart();
      const r=[[{transcript:'আমি ভাত খাবো'}]]; r[0].isFinal=true;
      setTimeout(()=>this.onresult&&this.onresult({results:r, resultIndex:0}),300); },100); }
    stop(){ window.__micLog.push('stop'); setTimeout(()=>{ this.onend&&this.onend(); },50); }
    abort(){ window.__micLog.push('abort'); this.onend&&this.onend(); }
  }
  window.SpeechRecognition = FakeSR; window.webkitSpeechRecognition = FakeSR;
  // a phone with no Bangla voice installed
  if (window.speechSynthesis) { const g=speechSynthesis.getVoices.bind(speechSynthesis); speechSynthesis.getVoices=()=>[{lang:'en-US',name:'English'}]; }
})();
"""

with sync_playwright() as pw:
    browser = pw.chromium.launch(args=['--use-fake-ui-for-media-stream'])
    ctx = browser.new_context(viewport=PHONE, device_scale_factor=2, is_mobile=True, has_touch=True, locale='bn-BD')
    ctx.add_init_script(MIC_MOCK)
    page = ctx.new_page()
    page.set_default_timeout(12000)
    def tab(name):
        if page.evaluate("document.body.dataset.nav") != '1': page.evaluate("goTo('home')"); page.wait_for_timeout(300)
        page.click(f'#nav [data-tab="{name}"]'); page.wait_for_timeout(500)
    def hashgo(h):
        page.evaluate(f"location.hash='{h}'"); page.wait_for_timeout(900)
    errors, dialogs, tts_calls = [], [], []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.on('console', lambda m: m.type == 'error' and 'favicon' not in m.text and errors.append(m.text))
    page.on('dialog', lambda d: (dialogs.append(d.message), d.accept()))
    # the server voice needs internet; answer it with a short silent MP3 so the test does not depend on Google
    def tts_route(route):
        tts_calls.append(route.request.url)
        route.fulfill(status=200, content_type='audio/mpeg', body=bytes.fromhex('fffb9064') + b'\x00' * 400)
    page.route(re.compile(r'.*api/tts\.php.*'), tts_route)

    # ---------------- 1. Start and navigation
    def t():
        t0 = time.time(); page.goto(BASE, wait_until='networkidle'); dt = (time.time() - t0) * 1000
        shot = snap(page, '01_splash')
        page.click('[data-go="choice"]'); page.wait_for_timeout(400); snap(page, '02_choice')
        page.click('#screen-choice [data-go="translate"]'); page.wait_for_timeout(400)
        ok = page.evaluate("document.body.dataset.screen") == 'translate' and not errors
        case('UI-01', 'Start & navigation', 'App opens, "Start Translating" → "Continue without Login" reaches the translator', 'Open home page, tap the two buttons', 'Translator screen, no script errors', f"{dt:.0f} ms load, screen={page.evaluate('document.body.dataset.screen')}, errors={errors[:2]}", ok, shot)
    safe('UI-01', 'Start & navigation', 'App opens and reaches the translator', 'Open home page', 'Translator screen', t)

    def t():
        seen = {}
        for t_ in ['home', 'translate', 'history', 'dictionary', 'profile']:
            tab(t_)
            seen[t_] = page.evaluate("document.body.dataset.screen")
        tab('learn')
        seen['learn (guest)'] = page.evaluate("document.body.dataset.screen")
        ok = all(seen[k] == k for k in ['home', 'translate', 'history', 'dictionary', 'profile']) and seen['learn (guest)'] == 'login'
        case('UI-02', 'Start & navigation', 'Bottom menu opens every screen; Learn asks a guest to log in', 'Tap each of the 6 bottom buttons', 'Each screen opens; Learn → Log in', seen, ok)
    safe('UI-02', 'Start & navigation', 'Bottom menu opens every screen', 'Tap each bottom button', 'Each screen opens', t)

    def t():
        tab('home')
        page.locator('#map-home .pin[data-d="Sylheti"]').first.dispatch_event('click'); page.wait_for_timeout(1200)
        txt = page.inner_text('#bubble'); shot = snap(page, '03_home_map')
        case('UI-03', 'Start & navigation', 'Tapping a district on the map shows a sample word of that dialect', 'Tap Sylhet on the map', 'Bubble with a Sylheti word', txt.replace('\n', ' ')[:90], 'Sylhet' in txt or 'সিলেট' in txt, shot)
    safe('UI-03', 'Start & navigation', 'Map pin shows a sample word', 'Tap Sylhet', 'Bubble', t)

    # ---------------- 2. Translator
    def pick(side, label):
        page.click(f'#{side}-btn'); page.wait_for_timeout(300)
        page.click(f'#sheet .lang-row[data-side="{side}"][data-id="{label}"]'); page.wait_for_timeout(300)

    def t():
        tab('translate')
        pick('from', 'standard'); pick('to', 'Chattogram')
        page.fill('#dialect-input', 'আমি ভাত খাবো'); page.click('#translate-btn')
        page.wait_for_selector('#result-card.show', timeout=8000); page.wait_for_timeout(400)
        res = page.inner_text('#result-text'); boxes = page.locator('#result-pairs .pair').count()
        shot = snap(page, '04_translate_result')
        case('UI-04', 'Translator', 'Type a sentence, choose Chattogram, press Translate', 'আমি ভাত খাবো, Standard → Chattogram', 'Result আঁই ভাত খাইয়ুম with 3 word boxes', f'{res} | {boxes} boxes', nfc('খাইয়ুম') in nfc(res) and boxes == 3, shot)
    safe('UI-04', 'Translator', 'Translate a typed sentence', 'Type + Translate', 'Result shown', t)

    def t():
        # D35: the Bridge screen stayed empty (a helper function was missing)
        page.click('[data-act="openBridge"]'); page.wait_for_timeout(1500)
        right = page.locator('#bridge-card .bpill.r').all_inner_texts(); shot = snap(page, '04b_bridge')
        page.evaluate("goTo('translate')"); page.wait_for_timeout(500)
        case('UI-41', 'Translator', 'The Bridge screen shows each word joined to its translation', 'Translate আমি ভাত খাবো → See the bridge', '3 pairs, right side আঁই ভাত খাইয়ুম', ' '.join(right) or 'empty', [nfc(x) for x in right] == [nfc(x) for x in ['আঁই', 'ভাত', 'খাইয়ুম']], shot)
    safe('UI-41', 'Translator', 'Bridge shows the word pairs', 'See the bridge', 'pairs', t)

    def t():
        # D36: from one dialect to another the word boxes showed the Standard Bangla word in between
        pick('from', 'Noakhali'); pick('to', 'Chattogram')
        page.fill('#dialect-input', 'আঁই ভাত খামু'); page.click('#translate-btn'); page.wait_for_timeout(1800)
        big = page.locator('#result-pairs .pair b').all_inner_texts(); shot = snap(page, '04c_dialect_to_dialect')
        pick('from', 'standard')
        case('UI-42', 'Translator', 'Dialect to dialect: the word boxes show the target dialect word', 'Noakhali আঁই ভাত খামু → Chattogram', 'Boxes আঁই, ভাত, খাইয়ুম (not খাবো)', ', '.join(big), nfc('খাইয়ুম') in [nfc(x) for x in big] and 'খাবো' not in big, shot)
    safe('UI-42', 'Translator', 'Dialect-to-dialect word boxes', 'Noakhali → Chattogram', 'target word', t)

    def t():
        page.click('[data-act="clearText"]') if page.locator('#clear-btn.show').count() else None
        page.fill('#dialect-input', ''); page.type('#dialect-input', 'তুমি কোথায় যাবে', delay=20)
        t0 = time.time(); page.wait_for_function("document.querySelector('#result-text').textContent.includes('কডে') || document.querySelector('#result-text').textContent.includes('হন্ডে') || document.querySelector('#result-text').textContent.length>0 && !document.querySelector('#result-text').textContent.includes('খাইয়ুম')", timeout=6000)
        dt = (time.time() - t0) * 1000
        case('UI-05', 'Translator', 'Live translation appears while typing (no button press)', 'Type তুমি কোথায় যাবে', 'Result updates by itself within 3 s', f"{page.inner_text('#result-text')} after {dt:.0f} ms", dt < 3000)
    safe('UI-05', 'Translator', 'Live translation while typing', 'Type', 'Result updates', t)

    def t():
        before = (page.inner_text('#from-label'), page.inner_text('#to-label'))
        page.click('[data-act="swapLang"]'); page.wait_for_timeout(600)
        after = (page.inner_text('#from-label'), page.inner_text('#to-label'))
        case('UI-06', 'Translator', 'Swap button exchanges input and output languages', 'Tap ⇄', 'Languages change places', f'{before} → {after}', after[0] == before[1] and after[1] == before[0])
        page.click('[data-act="swapLang"]'); page.wait_for_timeout(300)
    safe('UI-06', 'Translator', 'Swap languages', 'Tap swap', 'Exchanged', t)

    def t():
        ctx.grant_permissions(['clipboard-read', 'clipboard-write'], origin=BASE.rstrip('/'))
        page.fill('#dialect-input', 'আমি ভাত খাবো'); page.click('#translate-btn'); page.wait_for_timeout(1200)
        page.click('#copy-btn'); page.wait_for_timeout(300)
        clip = page.evaluate("navigator.clipboard.readText()")
        case('UI-07', 'Translator', 'Copy button copies the translation', 'Tap Copy', 'Clipboard has the result', clip, clip.strip() == page.inner_text('#result-text').strip() and clip.strip() != '')
    safe('UI-07', 'Translator', 'Copy result', 'Tap Copy', 'Copied', t)

    def t():
        page.locator('#result-pairs .pair').nth(2).click(); page.wait_for_timeout(500)
        txt = page.inner_text('#sheet-body'); shot = snap(page, '05_word_card')
        page.keyboard.press('Escape'); page.wait_for_timeout(300)
        case('UI-08', 'Translator', 'Tapping a word box shows that word\'s meaning and source', 'Tap the 3rd word box', 'Card with the Standard Bangla meaning', txt.replace('\n', ' ')[:100], 'Standard Bangla meaning' in txt or 'Evidence' in txt, shot)
    safe('UI-08', 'Translator', 'Word box card', 'Tap word', 'Card', t)

    def t():
        page.fill('#dialect-input', ''); page.click('#translate-btn'); page.wait_for_timeout(400)
        toast = page.inner_text('#toast')
        case('UI-09', 'Translator', 'Pressing Translate with an empty box shows a hint', 'Empty box + Translate', '"Type, speak, or scan something first"', toast, 'Type, speak' in toast)
    safe('UI-09', 'Translator', 'Empty translate hint', 'Empty', 'Hint', t)

    def t():
        page.fill('#dialect-input', 'আমি ভাত খাবো। তুমি কোথায় যাবে?'); page.click('#translate-btn'); page.wait_for_timeout(1500)
        res = page.inner_text('#result-text')
        case('UI-10', 'Translator', 'Punctuation is shown in the result', 'Two sentences with । and ?', 'Result keeps । and ?', res, '।' in res and res.strip().endswith('?'))
    safe('UI-10', 'Translator', 'Punctuation shown', 'Two sentences', 'Kept', t)

    def t():
        pick('from', 'english'); pick('to', 'standard')
        page.fill('#dialect-input', 'I will eat rice'); page.click('#translate-btn'); page.wait_for_timeout(1500)
        res = page.inner_text('#result-text'); boxes = page.locator('#result-pairs .pair b').all_inner_texts()
        shot = snap(page, '23_english_grammar')
        case('UI-40', 'Translator', 'English sentence is translated with Bangla word order and verb form', 'English → Standard: I will eat rice', 'আমি ভাত খাবো; eat box shows খাবো', f'{res} | boxes {boxes}', nfc(res.strip()) == nfc('আমি ভাত খাবো') and nfc('খাবো') in [nfc(x) for x in boxes], shot)
        pick('from', 'standard'); pick('to', 'Chattogram')
    safe('UI-40', 'Translator', 'English grammar in the app', 'English', 'আমি ভাত খাবো', t)

    # ---------------- 3. Voice
    def t():
        page.fill('#dialect-input', ''); page.click('#mic-btn')
        page.wait_for_function("document.querySelector('#dialect-input').value.includes('ভাত')", timeout=5000)
        listening = page.evaluate("document.querySelector('#compose').classList.contains('listening')")
        shot = snap(page, '06_voice_listening')
        page.click('#wavebox .stopbtn'); page.wait_for_timeout(2500)
        val = page.input_value('#dialect-input'); still = page.evaluate("document.querySelector('#compose').classList.contains('listening')")
        log = page.evaluate('window.__micLog')
        case('UI-11', 'Voice', 'Voice input writes the spoken Bangla words into the box', 'Tap mic, "speak" আমি ভাত খাবো', 'Text appears in Bangla letters', val, 'আমি ভাত খাবো' in val and listening, shot)
        case('UI-12', 'Voice', 'Stop button ends listening and keeps the text', 'Tap ■ Stop', 'Listening stops, text stays', f'listening={still}, text={val}, mic log={log}', not still and 'ভাত' in val and 'stop' in log)
    safe('UI-11', 'Voice', 'Voice input', 'Mic', 'Text', t)

    def t():
        tts_calls.clear()
        page.click('#translate-btn'); page.wait_for_timeout(1200)
        page.click('#speak-btn'); page.wait_for_timeout(2500)
        case('UI-13', 'Voice', 'Listen works on a phone with no Bangla voice (the app\'s server voice is used)', 'Tap 🔊 with no Bangla voice installed', 'Sound requested from api/tts.php', tts_calls[:1], len(tts_calls) >= 1 and 'tts.php' in tts_calls[0])
    safe('UI-13', 'Voice', 'Listen fallback', 'Tap speaker', 'tts.php', t)

    # ---------------- 4. Scan (image → text)
    def t():
        img = os.path.join(SHOTS, '_scan_input.png')
        p2 = browser.new_page(viewport={'width': 900, 'height': 220})
        p2.set_content('<body style="margin:0;background:#fff"><div style="font:64px \'Noto Sans Bengali\';padding:50px 40px;color:#111">আমি ভাত খাবো</div></body>')
        p2.screenshot(path=img); p2.close()
        page.fill('#dialect-input', '')
        page.set_input_files('#image-input', img)
        page.wait_for_function("document.querySelector('#scan-status') && /Text found|No readable|Could not/.test(document.querySelector('#scan-status').textContent)", timeout=60000)
        val = page.input_value('#dialect-input'); status = page.inner_text('#scan-status'); shot = snap(page, '07_scan')
        case('UI-14', 'Scan', 'Scanning a photo of printed Bangla puts the text in the box', 'Choose an image showing আমি ভাত খাবো', 'Text read: আমি ভাত খাবো', f'{status} | {val}', 'ভাত' in val and 'খাবো' in val, shot)
    safe('UI-14', 'Scan', 'Scan image', 'Image', 'Text', t)

    def t():
        bad = os.path.join(SHOTS, '_not_image.txt'); open(bad, 'w').write('hello')
        page.set_input_files('#image-input', {'name': 'notes.txt', 'mimeType': 'text/plain', 'buffer': b'hello'})
        page.wait_for_timeout(500); toast = page.inner_text('#toast')
        case('UI-15', 'Scan', 'Choosing a file that is not an image is refused with a message', 'Choose notes.txt', '"Please choose an image file"', toast, 'image file' in toast)
    safe('UI-15', 'Scan', 'Non-image refused', 'txt', 'Message', t)

    # ---------------- 5. Dictionary
    def t():
        tab('dictionary'); page.wait_for_selector('#dict-list .drow', timeout=8000)
        n = page.locator('#dict-list .drow').count(); count = page.inner_text('#dict-count'); shot = snap(page, '08_dictionary')
        case('UI-16', 'Dictionary', 'Words screen lists dictionary words', 'Open Words', '30 words on the first page and a total count', f'{n} rows, {count}', n == 30 and 'available' in count, shot)
        page.fill('#dict-q', 'খাই'); page.wait_for_timeout(1500)
        rows = page.locator('#dict-list .drow .a').all_inner_texts()
        case('UI-17', 'Dictionary', 'Searching the word list filters it', 'Type খাই', 'Only matching words', f'{len(rows)} rows, first {rows[:2]}', len(rows) > 0 and page.inner_text('#dict-count').find('matches') > 0)
        page.fill('#dict-q', ''); page.wait_for_timeout(800)
        page.select_option('#dict-language', 'chakma'); page.wait_for_timeout(1500)
        sub = page.inner_text('#dict-sub')
        case('UI-18', 'Dictionary', 'Changing the language shows that language\'s words', 'Choose Chakma', 'Chakma words listed', sub, 'Chakma' in sub and page.locator('#dict-list .drow').count() > 0)
        page.select_option('#dict-language', 'Chattogram'); page.wait_for_timeout(1200)
    safe('UI-16', 'Dictionary', 'Word list', 'Open', 'Rows', t)

    def t():
        word = 'ইউআইপরীক্ষা' + RUN[:2]
        page.click('[data-act="addWord"]'); page.wait_for_timeout(300)
        page.fill('#wf-word', word); page.fill('#wf-mean', 'পরীক্ষা'); shot = snap(page, '09_add_word_form')
        page.click('[data-act="saveWordForm"]'); page.wait_for_timeout(1200)
        page.fill('#dict-q', word); page.wait_for_timeout(1500)
        found = page.locator('#dict-list .drow').count()
        case('UI-19', 'Dictionary', 'Add a word through the ＋ form', f'＋ Add a word: {word} = পরীক্ষা', 'Saved and found by search', f'{found} found', found == 1, shot)
        page.click('#dict-list .dedit'); page.wait_for_timeout(300)
        pre = (page.input_value('#wf-word'), page.input_value('#wf-mean'))
        page.fill('#wf-mean', 'যাচাই'); page.click('[data-act="saveWordForm"]'); page.wait_for_timeout(1500)
        b = page.locator('#dict-list .drow .b').first.inner_text() if page.locator('#dict-list .drow').count() else ''
        case('UI-20', 'Dictionary', 'Edit form (✎) opens with the word and meaning filled in, and saves changes', 'Tap ✎, change meaning to যাচাই', 'Form pre-filled; list shows যাচাই', f'form {pre} → list {b}', pre[0] == word and pre[1] == 'পরীক্ষা' and b == 'যাচাই')
        page.click('#dict-list .dedit'); page.wait_for_timeout(300)
        page.click('[data-act="deleteWordForm"]'); page.wait_for_timeout(1500)
        left = page.locator('#dict-list .drow').count()
        case('UI-21', 'Dictionary', 'Delete a word from the edit form (asks to confirm first)', 'Tap ✎ → Delete → OK', 'Confirmation shown; word removed', f'confirm dialogs={dialogs[-1:] }, rows left={left}', left == 0 and any('Delete' in d for d in dialogs))
        page.fill('#dict-q', ''); page.wait_for_timeout(600)
    safe('UI-19', 'Dictionary', 'Add/edit/delete word', 'Form', 'Saved', t)

    def t():
        xss = '<img src=x onerror="window.__xss=1">ক' + RUN[:2]
        requests.post(BASE + 'api/edit_word.php', json={'language': 'Chattogram', 'word': xss, 'meaning': '<script>window.__xss=2</script>'}, timeout=30)
        page.fill('#dict-q', '<img'); page.wait_for_timeout(1500)
        shown = page.locator('#dict-list .drow .a').first.inner_text() if page.locator('#dict-list .drow').count() else ''
        hit = page.evaluate('window.__xss || 0'); imgs = page.locator('#dict-list img').count()
        shot = snap(page, '10_xss_check')
        case('UI-22', 'Security', 'HTML/script typed into a dictionary word is shown as plain text, never run', xss, 'Shown as text; no script runs', f'shown="{shown[:40]}", script ran={hit}, img tags={imgs}', hit == 0 and imgs == 0 and '<img' in shown, shot)
        requests.post(BASE + 'api/edit_word.php', json={'language': 'Chattogram', 'word': xss, 'meaning': '', 'action': 'delete'}, timeout=30)
        page.fill('#dict-q', ''); page.wait_for_timeout(500)
    safe('UI-22', 'Security', 'XSS', 'payload', 'text', t)

    # ---------------- 6. Account, history, learn, quiz
    email = f'ui_{RUN}@example.com'
    def t():
        hashgo('register')
        page.fill('#register-name', 'Muskan Test'); page.fill('#register-email', email); page.fill('#register-password', 'Passw0rd!x')
        shot = snap(page, '11_register')
        page.click('[data-act="doRegister"]'); page.wait_for_timeout(1500)
        tab('profile')
        body = page.inner_text('#profile-body'); shot2 = snap(page, '12_profile')
        case('UI-23', 'Account', 'Create an account from the Register screen', 'Name, email, password → Create', 'Logged in; Profile shows the name', body.replace('\n', ' ')[:80], 'Muskan Test' in body, shot2)
    safe('UI-23', 'Account', 'Register', 'Form', 'Profile', t)

    def t():
        tab('translate')
        page.fill('#dialect-input', 'আমি বাড়ি যাবো'); page.click('#translate-btn'); page.wait_for_timeout(1500)
        tab('history')
        txt = page.inner_text('#history-list'); shot = snap(page, '13_history')
        case('UI-24', 'History', 'A translation appears in History', 'Translate আমি বাড়ি যাবো, open History', 'Entry listed', txt.replace('\n', ' ')[:80], 'যাবো' in txt, shot)
    safe('UI-24', 'History', 'History', 'Translate', 'Entry', t)

    def t():
        tab('learn')
        fr = page.frame_locator('#learn-frame')
        fr.locator('#chosenCourse').wait_for(timeout=8000)
        stmt = fr.locator('#chosenCourse').inner_text(); picker_hidden = fr.locator('#pairPicker').is_hidden()
        shot = snap(page, '14_learn_path')
        case('UI-25', 'Learning', 'Learn shows the chosen pair as one line (e.g. "Noakhali → Standard Bangla")', 'Open Learn after login', 'One-line statement, picker hidden', f'"{stmt}", picker hidden={picker_hidden}', '→' in stmt and picker_hidden, shot)
        fr.locator('#changePair').click(); page.wait_for_timeout(300)
        shown = fr.locator('#pairPicker').is_visible()
        fr.locator('#targetLang').select_option('chattogram-bn'); fr.locator('#sourceLang').select_option('standard'); page.wait_for_timeout(1500)
        stmt2 = fr.locator('#chosenCourse').inner_text(); shot = snap(page, '15_learn_change')
        fr.locator('#changePair').click()
        case('UI-26', 'Learning', '"Change" opens the language picker and the statement follows the new choice', 'Change → Standard Bangla → Chattogram', 'Statement updates', f'picker shown={shown}, now "{stmt2}"', shown and 'চট্টগ্রাম' in stmt2, shot)
        fr.locator('.stageBtn', has_text='শুরু ·').first.click(); page.wait_for_timeout(1200)
        fr.locator('#answers .answer').first.wait_for(timeout=6000)
        opts = fr.locator('#answers .answer').count()
        fr.locator('#answers .answer').first.click(); page.wait_for_timeout(1200)
        fb = fr.locator('#feedback').inner_text(); shot = snap(page, '16_learn_lesson')
        case('UI-27', 'Learning', 'Start the Sounds lesson and answer a question', 'Start → tap an answer', 'Feedback "ঠিক হয়েছে!" or "আবার চেষ্টা করুন" (instructions in the learner\'s language)', f'{opts} options, feedback: {fb[:60]}', opts >= 2 and ('ঠিক হয়েছে' in fb or 'আবার চেষ্টা' in fb), shot)
        fr.locator('#goBack').click(); page.wait_for_timeout(1200)
        qb = fr.locator('.stageBtn', has_text='কুইজ শুরু')
        if qb.count():
            qb.first.click(); page.wait_for_timeout(1500)
            n = fr.locator('#answers .answer').count(); prompt = fr.locator('#prompt').inner_text()
            fr.locator('#answers .answer').first.click(); page.wait_for_timeout(500)
            fb = fr.locator('#feedback').inner_text(); shot = snap(page, '17_learn_quiz')
            case('UI-28', 'Learning', 'Word quiz for Standard → Chattogram works', 'Words → Start quiz → answer', 'Question with 4 options and feedback', f'{prompt[:40]} | {n} options | {fb[:40]}', n == 4 and ('ঠিক হয়েছে' in fb or 'আবার চেষ্টা' in fb), shot)
            fr.locator('#goBack').click(); page.wait_for_timeout(600)
        else:
            case('UI-28', 'Learning', 'Word quiz for Standard → Chattogram works', 'Words → Start quiz', 'Quiz button', 'No Start quiz button', False)
    safe('UI-25', 'Learning', 'Learn page', 'Open', 'Statement', t)

    def t():
        hashgo('quiz')
        page.wait_for_selector('#quiz-body button', timeout=8000)
        opts = page.locator('#quiz-body [data-act="answer"]').count()
        page.locator('#quiz-body [data-act="answer"]').first.click(); page.wait_for_timeout(600)
        marked = page.locator('#quiz-body .right, #quiz-body .correct, #quiz-body .ok').count()
        shot = snap(page, '18_quiz')
        case('UI-29', 'Quiz', 'Dialect quiz: answering marks the right option', 'Open quiz, tap an option', '4 options; correct one highlighted', f'{opts} options, highlighted={marked}', opts == 4 and marked >= 1, shot)
    safe('UI-29', 'Quiz', 'Quiz', 'Answer', 'Marked', t)

    def t():
        hashgo('profile')
        page.click('[data-act="doLogout"]'); page.wait_for_timeout(1000)
        hashgo('login')
        page.fill('#login-email', email); page.fill('#login-password', 'wrong-password'); page.click('[data-act="doLogin"]'); page.wait_for_timeout(900)
        bad = page.inner_text('#toast')
        page.fill('#login-password', 'Passw0rd!x'); page.click('[data-act="doLogin"]'); page.wait_for_timeout(1500)
        who = page.evaluate("fetch('api/auth.php?action=me').then(r=>r.json()).then(d=>d.user&&d.user.email)")
        case('UI-30', 'Account', 'Log out, then a wrong password shows a message and the right one logs in', 'Logout → wrong password → right password', 'Error message, then logged in', f'message "{bad}", logged in as {who}', ('Incorrect' in bad or 'wrong' in bad.lower()) and who == email)
    safe('UI-30', 'Account', 'Login', 'Logout/login', 'OK', t)

    # ---------------- 7. Mobile layout, PWA, offline
    def t():
        bad = {}
        for w in (360, 390, 414):
            page.set_viewport_size({'width': w, 'height': 780})
            for scr in ['home', 'translate', 'dictionary', 'history', 'profile', 'learn']:
                page.evaluate(f"goTo('{scr}')"); page.wait_for_timeout(350)
                over = page.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth")
                if over > 1: bad[f'{scr}@{w}'] = over
        page.set_viewport_size(PHONE)
        case('UI-31', 'Mobile layout', 'No sideways scrolling on phone widths 360, 390 and 414 px', '6 screens × 3 widths', 'Nothing wider than the screen', bad or 'none', not bad)
    safe('UI-31', 'Mobile layout', 'No sideways scroll', 'widths', 'none', t)

    def t():
        small = page.evaluate("""[...document.querySelectorAll('#nav button, #translate-btn, #mic-btn, #cam-btn, #speak-btn, #copy-btn')].filter(b=>b.offsetParent).map(b=>{const r=b.getBoundingClientRect();return [b.id||b.dataset.tab, Math.round(r.width), Math.round(r.height)]}).filter(x=>x[1]<40||x[2]<40)""")
        page.evaluate("goTo('translate')"); page.wait_for_timeout(300)
        small2 = page.evaluate("""[...document.querySelectorAll('#translate-btn, #mic-btn, #cam-btn, #from-btn, #to-btn')].map(b=>{const r=b.getBoundingClientRect();return [b.id, Math.round(r.width), Math.round(r.height)]}).filter(x=>x[1]<40||x[2]<40)""")
        noname = page.evaluate("""[...document.querySelectorAll('button')].filter(b=>b.checkVisibility({visibilityProperty:true}) && !b.closest('[aria-hidden="true"]') && !(b.innerText.trim()||b.getAttribute('aria-label')||b.title)).map(b=>b.outerHTML.slice(0,120))""")
        case('UI-32', 'Mobile layout', 'Main buttons are big enough to tap and every visible button has a name for screen readers', 'Measure buttons', 'At least 40 × 40 px; no unnamed buttons', f'small={small + small2}, unnamed={noname}', not (small + small2) and not noname)
    safe('UI-32', 'Mobile layout', 'Tap targets', 'measure', 'ok', t)

    def t():
        man = requests.get(BASE + 'manifest.json', timeout=30).json()
        icons_ok = all(requests.get(BASE + i['src'], timeout=30).status_code == 200 for i in man.get('icons', []))
        sizes = [i.get('sizes') for i in man.get('icons', [])]
        ok = man.get('display') == 'standalone' and man.get('start_url') and icons_ok and any('192' in str(s) for s in sizes) and any('512' in str(s) for s in sizes)
        case('UI-33', 'Installable app', 'App manifest is complete so the phone can install it', 'manifest.json', 'Name, standalone, start URL, 192 and 512 px icons', f"{man.get('short_name')}, {man.get('display')}, icons {sizes}, all load={icons_ok}", ok)
        page.goto(BASE, wait_until='networkidle'); page.wait_for_timeout(1500)
        sw = page.evaluate("navigator.serviceWorker.ready.then(r=>r.active && r.active.state)")
        page.reload(wait_until='networkidle'); page.wait_for_timeout(1000)
        ctrl = page.evaluate("!!navigator.serviceWorker.controller")
        case('UI-34', 'Installable app', 'Offline helper (service worker) is installed and controls the page', 'Open app twice', 'Service worker active', f'state={sw}, controlling={ctrl}', sw == 'activated' and ctrl)
        ctx.set_offline(True)
        try:
            page.reload(wait_until='domcontentloaded'); page.wait_for_timeout(1500)
            shell = page.locator('#screen-splash, #screen-home').count() > 0 and page.locator('#nav').count() == 1
            shot = snap(page, '19_offline')
        finally:
            ctx.set_offline(False)
        page.wait_for_timeout(2500)
        user_back = page.evaluate("fetch('api/auth.php?action=me').then(r=>r.json()).then(d=>!!d.user)")
        page.evaluate("goTo('learn')"); page.wait_for_timeout(800)
        scr = page.evaluate("document.body.dataset.screen")
        case('UI-39', 'Installable app', 'After opening offline, the logged-in account is recognised again when the internet returns (no reload)', 'Open offline as a logged-in user, go back online, open Learn', 'Learn opens (not the login screen)', f'screen after reconnect: {scr}', user_back and scr == 'learn')
        page.reload(wait_until='load'); page.wait_for_timeout(1500)
        case('UI-35', 'Installable app', 'App screen still opens with no internet (offline)', 'Turn network off, reload', 'App shell shows', f'shell shown={shell}', shell, shot)
    safe('UI-33', 'Installable app', 'PWA', 'manifest', 'ok', t)

    # ---------------- 8. Checks added after the first run (bugs found and fixed)
    def t():
        lw = 'দীর্ঘ' * 20
        requests.post(BASE + 'api/edit_word.php', json={'language': 'Chattogram', 'word': lw, 'meaning': 'অনেক' * 25}, timeout=30)
        page.set_viewport_size({'width': 360, 'height': 740})
        page.evaluate("goTo('dictionary')"); page.wait_for_timeout(900)
        page.fill('#dict-q', 'দীর্ঘদীর্ঘ'); page.wait_for_timeout(1500)
        d_over = page.evaluate("Math.max(0,...[...document.querySelectorAll('#dict-list .drow-wrap')].map(e=>e.scrollWidth-e.clientWidth))")
        shot = snap(page, '20_long_word')
        page.evaluate("goTo('translate')"); page.fill('#dialect-input', 'আমি ' + 'ভাত' * 40 + ' খাবো'); page.click('#translate-btn'); page.wait_for_timeout(1500)
        r_over = page.evaluate("document.querySelector('#result-card').scrollWidth-document.querySelector('#result-card').clientWidth")
        shot2 = snap(page, '21_long_result')
        page.set_viewport_size(PHONE); page.fill('#dict-q', '') if page.locator('#dict-q').is_visible() else None
        requests.post(BASE + 'api/edit_word.php', json={'language': 'Chattogram', 'word': lw, 'meaning': '', 'action': 'delete'}, timeout=30)
        case('UI-37', 'Mobile layout', 'A very long word (100 letters, no spaces) wraps inside its box', '100-letter word in Words and in a translation at 360 px', 'Nothing sticks out of the card', f'word row overflow {d_over}px, result card overflow {r_over}px', d_over <= 1 and r_over <= 1, shot)
    safe('UI-37', 'Mobile layout', 'Long word wraps', 'long word', 'wraps', t)

    def t():
        n0 = len(dialogs)
        page.evaluate("goTo('learn')"); page.wait_for_timeout(2500)
        fr = page.frame_locator('#learn-frame')
        fr.locator('#chosenCourse').wait_for(timeout=8000)
        if fr.locator('#pairPicker').is_hidden(): fr.locator('#changePair').click()
        fr.locator('#sourceLang').select_option('standard'); page.wait_for_timeout(800); fr.locator('#targetLang').select_option('mro'); page.wait_for_timeout(1500)
        fr.locator('.stageBtn', has_text='কুইজ শুরু').first.click(); page.wait_for_timeout(1500)
        msg = fr.locator('#learnMsg').inner_text() if fr.locator('#learnMsg').count() else ''
        shot = snap(page, '22_learn_message')
        fr.locator('#targetLang').select_option('chattogram-bn'); page.wait_for_timeout(800); fr.locator('#changePair').click()
        case('UI-38', 'Learning', 'When a quiz has too little data, a message is shown on the page (no pop-up box)', 'Standard Bangla → Mro → Start quiz', 'Message line, no alert() pop-up', f'message "{msg[:70]}", pop-ups={len(dialogs) - n0}', bool(msg) and len(dialogs) == n0, shot)
    safe('UI-38', 'Learning', 'Inline message', 'Mro quiz', 'message', t)

    def t():
        # D37: the learning page was in English for every learner; a Mro speaker must see no English
        fr = page.frame_locator('#learn-frame')
        if fr.locator('#pairPicker').is_hidden(): fr.locator('#changePair').click()
        fr.locator('#sourceLang').select_option('mro'); page.wait_for_timeout(800); fr.locator('#targetLang').select_option('standard'); page.wait_for_timeout(1800)
        fr.locator('.stageBtn').nth(0).click(); page.wait_for_timeout(1500)
        txt = fr.locator('body').inner_text(); shot = snap(page, '23_learn_mro')
        eng = [w for w in re.findall(r'[A-Za-z]{3,}', txt) if w not in ('VashaSetu',)]
        fr.locator('#goBack').click(); page.wait_for_timeout(600)
        if fr.locator('#pairPicker').is_hidden(): fr.locator('#changePair').click()
        fr.locator('#sourceLang').select_option('standard'); page.wait_for_timeout(600); fr.locator('#targetLang').select_option('chattogram-bn'); page.wait_for_timeout(800); fr.locator('#changePair').click()
        case('UI-43', 'Learning', 'Instructions follow the learner\'s language: a Mro speaker sees pictures and symbols, no English', 'Learn: Mro → Standard Bangla → open the first lesson', 'No English words on the page', ', '.join(eng[:5]) or 'no English', not eng, shot)
    safe('UI-43', 'Learning', 'Learn page in the learner language', 'Mro learner', 'no English', t)

    case('UI-36', 'Start & navigation', 'No JavaScript errors during the whole test run', 'Console during all tests', 'No errors', errors[:3] or 'none', not [e for e in errors if 'ERR_INTERNET_DISCONNECTED' not in e and 'Failed to load resource' not in e])
    browser.close()

json.dump({'base': BASE, 'run': RUN, 'time': time.strftime('%Y-%m-%d %H:%M'), 'results': results}, open(os.path.join(HERE, 'results', 'ui_results.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
n = sum(r['result'] == 'Pass' for r in results)
print(f'{n}/{len(results)} UI tests passed')
sys.exit(0 if n == len(results) else 1)
