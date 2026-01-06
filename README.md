
# Arcaea Link Play Sticker Generator

一个用于生成 **Arcaea Link Play Sticker** 的小工具：前端调参、服务端渲染出图，得到可直接分享/嵌入的 PNG 链接。

## 功能
- 选择角色底图（`/img/*.png`）
- 文本输入（支持换行）
- 横向/竖向位置调节（x / y）
- 旋转（Rotate）
- 字体大小（Font Size）
- 行距/间距（Spacing）
- Curve：**未完全实现**（目前仅保留实验入口）

## 技术结构
- 前端：React SPA（构建后为纯静态文件）
- 后端：PHP（GD + FreeType）服务端渲染，输出 `image/png`
- 数据：
  - 角色列表：`/api/characters.json`
  - 角色图片：`/img/*.png`
  - 字体：`/api/fonts/*.ttf`
- 部署形态：**丢进任意 PHP + Nginx/Apache 的网站目录即可**，不需要额外 Nginx rewrite（`/api/*.php` 为真实文件）

## 快速部署
### 依赖
- PHP 8.x（建议 8.4）
- PHP 扩展：`gd`（需要 FreeType 支持）、`mbstring`

### 构建与上线
1. 安装依赖并构建
   ```bash
   npm ci
   npm run build

2. 将 `build/` 目录内的**所有内容**上传/覆盖到你的网站根目录
   构建产物应包含：

   * `index.html`
   * `static/`
   * `img/`
   * `api/`

3. 在浏览器打开站点即可使用

## API

### 1) 渲染出图

* `GET /api/render.php`
* 返回：`image/png`（可直接 `<img src="...">` 引用）

参数（常用）

* `ch`：角色 id（推荐，唯一）；也可兼容旧的 `character`/文件名风格（不建议）
* `text` / `t`：文本（UTF-8，支持换行）
* `x`：横向位置（基于 UI 坐标系）
* `y`：竖向位置（基于 UI 坐标系）
* `s`：字体大小（UI 语义的 px）
* `sp`：行距/间距（0-100，50 为基准）
* `r`：旋转强度（范围 -10~10，内部换算为弧度，保持与前端一致）
* `scale`：输出倍率（1~4）
* `curve`：0/1（实验）
* `font`：字体名（映射到 `/api/fonts/`）
* `download=1`：触发下载头

示例

```text
/api/render.php?ch=maya&text=something&x=148&y=58&s=42&sp=50&r=-2&scale=1
```

### 2) 角色列表

* `GET /api/characters.json`
* 返回：角色数组，包含默认参数（`defaultText`）与图片文件名等信息

### 3) 配置/统计（可选）

* `GET /api/config.php`
* `POST /api/log.php`（如果你不需要统计，可删掉并移除前端调用）

## 修改默认文案与默认参数

* `src/characters.json`：前端默认值（修改后需重新 `npm run build`）
* `public/api/characters.json`：API 默认值（构建会拷贝到 `build/api/characters.json`）

字段示例：

```json
{
  "id": 11,
  "character": "tairitsu",
  "img": "tairitsu.png",
  "defaultText": { "text": "something", "x": 148, "y": 58, "s": 42, "sp": 50, "r": -2 }
}
```

## 中文与字体

* `text` 可直接使用中文（JSON / URL 都支持 UTF-8）
* 需要字体文件包含中文字形，否则会显示方块/空白
  建议放入支持 CJK 的 TTF（如 Noto/思源系），并在 `font` 映射里设为默认

## 致谢 / 上游项目

本项目基于并改造自：

* [https://github.com/Rosemoe/arcaea-stickers](https://github.com/Rosemoe/arcaea-stickers)
  其上游为：
* [https://github.com/TheOriginalAyaka/sekai-stickers](https://github.com/TheOriginalAyaka/sekai-stickers)

## License

MIT
