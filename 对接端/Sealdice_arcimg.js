// ==UserScript==
// @name         Arcaea Stickers - arcimg
// @author       xuetao
// @version      1.0.0
// @description  生成 Arcaea Link Play Sticker：.arcimg 角色 / .arcimg <id> [text] [x] [y] [r] [fs] [sp]
// @timestamp    2026-01-06
// @license      MIT
// ==/UserScript==

(() => {
  const EXT_NAME = 'arcimg';
  const EXT_AUTHOR = 'xuetao';
  const EXT_VERSION = '1.0.0';

  // 站点根地址（可在 JS 插件配置里改 base_url）
  const DEFAULT_BASE_URL = 'https://arc-stickers.xuetao.host';

  let ext = seal.ext.find(EXT_NAME);
  if (!ext) {
    ext = seal.ext.new(EXT_NAME, EXT_AUTHOR, EXT_VERSION);
    seal.ext.register(ext);
  }

  // 可在 WebUI 插件配置里修改
  seal.ext.registerStringConfig(ext, 'base_url', DEFAULT_BASE_URL);
  seal.ext.registerIntConfig(ext, 'cache_ttl_sec', 600); // 角色表缓存秒数

  const getBaseUrl = () => {
    let v = seal.ext.getStringConfig(ext, 'base_url');
    if (!v) v = DEFAULT_BASE_URL;
    return String(v).replace(/\/+$/, '');
  };

  // 角色表缓存
  let cache = { ts: 0, list: null, byId: null, inFlight: null };
  const nowSec = () => Math.floor(Date.now() / 1000);

  async function loadCharacters(force = false) {
    const ttl = seal.ext.getIntConfig(ext, 'cache_ttl_sec') || 600;
    const age = nowSec() - cache.ts;

    if (!force && cache.list && cache.byId && age < ttl) return cache;
    if (cache.inFlight) return cache.inFlight;

    cache.inFlight = (async () => {
      const base = getBaseUrl();
      const url = `${base}/api/characters.json?_=${Date.now()}`;
      const resp = await fetch(url);
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const data = await resp.json();

      let list = [];
      if (Array.isArray(data)) list = data;
      else if (Array.isArray(data.characters)) list = data.characters;
      else if (data && typeof data === 'object') list = Object.values(data);

      const byId = new Map();
      for (const ch of list) {
        if (!ch) continue;
        const id = Number(ch.id);
        if (!Number.isFinite(id)) continue;
        byId.set(id, ch);
      }

      cache.ts = nowSec();
      cache.list = list;
      cache.byId = byId;
      cache.inFlight = null;
      return cache;
    })();

    try {
      return await cache.inFlight;
    } finally {
      cache.inFlight = null; // 失败允许下次重试
    }
  }

  // 支持 "xxx yyy" / 'xxx yyy' 形式的带空格文本
  function parseQuotedArgs(raw) {
    const args = [];
    let i = 0;
    while (i < raw.length) {
      while (i < raw.length && /\s/.test(raw[i])) i++;
      if (i >= raw.length) break;

      const ch = raw[i];
      if (ch === '"' || ch === "'") {
        const quote = ch;
        i++;
        let buf = '';
        while (i < raw.length) {
          const c = raw[i];
          if (c === '\\' && i + 1 < raw.length) {
            buf += raw[i + 1];
            i += 2;
            continue;
          }
          if (c === quote) {
            i++;
            break;
          }
          buf += c;
          i++;
        }
        args.push(buf);
      } else {
        let j = i;
        while (j < raw.length && !/\s/.test(raw[j])) j++;
        args.push(raw.slice(i, j));
        i = j;
      }
    }
    return args;
  }

  function numOr(val, def) {
    if (val === undefined || val === null || val === '') return def;
    const n = Number(val);
    return Number.isFinite(n) ? n : def;
  }

  function buildRenderUrl(base, id, text, x, y, r, fs, sp) {
    const q = [
      `ch=${encodeURIComponent(String(id))}`,
      `text=${encodeURIComponent(String(text ?? ''))}`,
      `x=${encodeURIComponent(String(x))}`,
      `y=${encodeURIComponent(String(y))}`,
      `r=${encodeURIComponent(String(r))}`,
      `s=${encodeURIComponent(String(fs))}`,
      `sp=${encodeURIComponent(String(sp))}`,
    ].join('&');
    return `${base}/api/render.php?${q}`;
  }

  function replyPrefix(msg) {
    const rid = msg.rawId ?? msg.rawID ?? msg.RawId ?? msg.RawID;
    if (rid === undefined || rid === null || rid === '') return '';
    return `[CQ:reply,id=${rid}]`;
  }

  const cmd = seal.ext.newCmdItemInfo();
  cmd.name = 'arcimg';
  cmd.help =
    'Arcaea贴纸生成：.arcimg 角色 / .arcimg <角色编号> [文本] [x] [y] [Rotate] [Font size] [Spacing]\n' +
    '文本含空格请用引号包住，例如：.arcimg 14 "hello world"';

  cmd.solve = async (ctx, msg, cmdArgs) => {
    const prefix = replyPrefix(msg);

    const raw = (cmdArgs.rawArgs ?? '').trim();
    const args = parseQuotedArgs(raw);

    if (args.length === 0) {
      seal.replyToSender(
        ctx,
        msg,
        prefix +
          'Arcaea贴纸生成：\n' +
          '.arcimg 角色 | 查看角色ID列表\n' +
          '.arcimg <角色编号> [文本] [x] [y] [旋转] [字体大小] [换行间距] | 生成图像\n' +
          '文本含空格请用引号包住，例如：.arcimg 14 "Hello World"\n\n' +
          '本扩展仅适合用于填入基本文本的操作，如果你想对文字大小位置和角度等进行细节调整，建议使用网页端作图：\n' +
          DEFAULT_BASE_URL
      );
      return seal.ext.newCmdExecuteResult(true);
    }

    // arcimg 角色
    if (args[0] === '角色' || String(args[0]).toLowerCase() === 'list') {
      const base = getBaseUrl();
      const text =
        `${prefix}可用的角色列表如下图，使用角色右下角的数字作为编号来生成\n` +
        `[CQ:image,file=${base}/api/preview_small.png]`;
      seal.replyToSender(ctx, msg, text);
      return seal.ext.newCmdExecuteResult(true);
    }

    // arcimg <id> ...
    const id = Number(args[0]);
    if (!Number.isFinite(id)) {
      seal.replyToSender(ctx, msg, prefix + '角色ID应为数字！查看角色ID列表用：.arcimg 角色');
      return seal.ext.newCmdExecuteResult(true);
    }

    // 从角色表取 name + 默认参数，失败则退回内置默认
    let chData = null;
    try {
      const { byId } = await loadCharacters(false);
      chData = byId?.get(id) ?? null;
    } catch (e) {
      console.log('[arcimg] fetch characters.json failed:', String(e));
    }

    const defaults = chData?.defaultText ?? {};
    const name = chData?.name ?? 'Unknown';

    const textArg = args.length >= 2 ? args[1] : (defaults.text ?? 'something');
    const x = numOr(args[2], defaults.x ?? 148);
    const y = numOr(args[3], defaults.y ?? 58);
    const r = numOr(args[4], defaults.r ?? -2);
    const fs = numOr(args[5], defaults.s ?? 42);
    const sp = numOr(args[6], defaults.sp ?? 50);

    const base = getBaseUrl();
    const url = buildRenderUrl(base, id, textArg, x, y, r, fs, sp);

    const info =
      `${prefix}${name}（${id}）\n` +
      `${textArg}\n` +
      `${x},${y},${r},${fs},${sp}\n` +
      `[CQ:image,file=${url}]`;

    seal.replyToSender(ctx, msg, info);
    return seal.ext.newCmdExecuteResult(true);
  };

  ext.cmdMap['arcimg'] = cmd;
})();
