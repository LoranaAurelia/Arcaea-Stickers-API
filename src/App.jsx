import SSFangTangTi from "./fonts/ShangShouFangTangTi.woff2";
import "./App.css";
import appConfig from "./config.json";
import { useState, useEffect, useMemo } from "react";
import characters from "./characters.json";
import Slider from "@mui/material/Slider";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Switch from "@mui/material/Switch";
import Snackbar from "@mui/material/Snackbar";
import Picker from "./components/Picker";
import Info from "./components/Info";
import getConfiguration from "./utils/config";
import log from "./utils/log";

const { ClipboardItem } = window;

function App() {
  const [config, setConfig] = useState(null);

  // using this to trigger the useEffect because lazy to think of a better way
  const [rand, setRand] = useState(0);
  useEffect(() => {
    async function doGetConfiguration() {
      try {
        const res = await getConfiguration();
        setConfig(res);
      } catch (error) {
        console.log(error);
      }
    }
    doGetConfiguration();
  }, [rand]);

  const [infoOpen, setInfoOpen] = useState(false);
  const handleClickOpen = () => {
    setInfoOpen(true);
  };
  const handleClose = () => {
    setInfoOpen(false);
  };

  const [openCopySnackbar, setOpenCopySnackbar] = useState(false);
  const handleSnackClose = (e, r) => {
    setOpenCopySnackbar(false);
  };

  const [character, setCharacter] = useState(5);
  const [text, setText] = useState(characters[character].defaultText.text);
  const [position, setPosition] = useState({
    x: characters[character].defaultText.x,
    y: characters[character].defaultText.y,
  });
  const [fontSize, setFontSize] = useState(characters[character].defaultText.s);
  const [spaceSize, setSpaceSize] = useState(50);
  const [rotate, setRotate] = useState(characters[character].defaultText.r);
  const [curve, setCurve] = useState(false);

const previewUrl = useMemo(() => {
  const base = appConfig.renderApiUrl || appConfig.apiUrl || "/api";
  const params = new URLSearchParams();
  params.set("ch", characters[character].id);
  params.set("text", text);
  params.set("x", Math.round(position.x));
  params.set("y", Math.round(position.y));
  params.set("s", Math.round(fontSize));
  params.set("sp", Math.round(spaceSize));
  params.set("r", rotate);
  if (curve) params.set("curve", "1");
  return `${base.replace(/\/$/, "")}/render.php?${params.toString()}`;
}, [character, text, position.x, position.y, fontSize, spaceSize, rotate, curve]);

const [debouncedPreviewUrl, setDebouncedPreviewUrl] = useState(previewUrl);

useEffect(() => {
  const t = setTimeout(() => setDebouncedPreviewUrl(previewUrl), 150);
  return () => clearTimeout(t);
}, [previewUrl]);

const fetchPreviewBlob = async (asDownload = false) => {
  const url = asDownload ? `${debouncedPreviewUrl}&download=1` : debouncedPreviewUrl;
  const res = await fetch(url, { cache: "force-cache" });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return await res.blob();
};
  useEffect(() => {
    setText(characters[character].defaultText.text);
    setPosition({
      x: characters[character].defaultText.x,
      y: characters[character].defaultText.y,
    });
    setRotate(characters[character].defaultText.r);
    setFontSize(characters[character].defaultText.s);
  }, [character]);
  const download = async () => {
  try {
    const blob = await fetchPreviewBlob(true);
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.download = `${characters[character].name}_arcst.png`;
    link.href = url;
    link.click();
    URL.revokeObjectURL(url);
    await log(characters[character].id, characters[character].name, "download");
    setRand(rand + 1);
  } catch (e) {
    console.error(e);
  }
};

  const copy = async () => {
  try {
    const blob = await fetchPreviewBlob(false);
    await navigator.clipboard.write([
      new ClipboardItem({
        "image/png": blob,
      }),
    ]);
    setOpenCopySnackbar(true);
    await log(characters[character].id, characters[character].name, "copy");
    setRand(rand + 1);
  } catch (e) {
    console.error(e);
  }
};

const copyLink = async () => {
  try {
    await navigator.clipboard.writeText(debouncedPreviewUrl);
    setOpenCopySnackbar(true);
  } catch (e) {
    console.error(e);
  }
};

  return (
    <div className="App">
      <Info open={infoOpen} handleClose={handleClose} config={config} />
      <div className="counter">
        Total Stickers you made: {config?.total || "Not available"}
      </div>
      <div className="container">
        <div className="vertical">
          <div className="canvas">
            <img src={debouncedPreviewUrl} alt="preview" width="296" height="256" draggable={false} />
          </div>
          <Slider
            value={curve ? 256 - position.y + fontSize * 3 : 256 - position.y}
            onChange={(e, v) =>
              setPosition({
                ...position,
                y: curve ? 256 + fontSize * 3 - v : 256 - v,
              })
            }
            min={0}
            max={256}
            step={1}
            orientation="vertical"
            track={false}
            color="secondary"
          />
        </div>
        <div className="horizontal">
          <Slider
            className="slider-horizontal"
            value={position.x}
            onChange={(e, v) => setPosition({ ...position, x: v })}
            min={0}
            max={296}
            step={1}
            track={false}
            color="secondary"
          />
          <div className="settings">
            <div>
              <label>Rotate: </label>
              <Slider
                value={rotate}
                onChange={(e, v) => setRotate(v)}
                min={-10}
                max={10}
                step={0.2}
                track={false}
                color="secondary"
              />
            </div>
            <div>
              <label>
                <nobr>Font size: </nobr>
              </label>
              <Slider
                value={fontSize}
                onChange={(e, v) => setFontSize(v)}
                min={10}
                max={100}
                step={1}
                track={false}
                color="secondary"
              />
            </div>
            <div>
              <label>
                <nobr>Spacing: </nobr>
              </label>
              <Slider
                value={spaceSize}
                onChange={(e, v) => setSpaceSize(v)}
                min={0}
                max={100}
                step={1}
                track={false}
                color="secondary"
              />
            </div>
            <div>
              <label>Curve (Beta): </label>
              <Switch
                checked={curve}
                onChange={(e) => setCurve(e.target.checked)}
                color="secondary"
              />
            </div>
          </div>
          <div className="text">
            <TextField
              label="Text"
              size="small"
              color="secondary"
              value={text}
              multiline={true}
              fullWidth
              onChange={(e) => setText(e.target.value)}
            />
          </div>
          <div className="picker">
            <Picker setCharacter={setCharacter} />
          </div>
          <div className="buttons">
            <Button color="secondary" onClick={copy}>
              copy
            </Button>
            <Button color="secondary" onClick={copyLink}>
              copy link
            </Button>
            <Button color="secondary" onClick={download}>
              download
            </Button>
          </div>
        </div>
        <div className="footer">
          <Button color="secondary" onClick={handleClickOpen}>
            About
          </Button>
        </div>
      </div>
      <Snackbar
        anchorOrigin={{ vertical: "bottom", horizontal: "center" }}
        open={openCopySnackbar}
        onClose={handleSnackClose}
        message="Copied to clipboard."
        key="copy"
        autoHideDuration={1500}
      />
    </div>
  );
}

export default App;
