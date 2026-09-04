"use client"

import { Check, Copy, Link2, RefreshCw, ShieldOff } from "lucide-react"
import { useEffect, useState, type ReactNode } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { API_URL, ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

interface CodeTab {
  /** Short technical label — cURL / ESP32 / Serial. Proper nouns, untranslated. */
  label: string
  code: string
}

/**
 * Professional dark code panel with language tabs and per-tab copy — the
 * Stripe-docs pattern. Deliberately dark in BOTH themes: terminal samples
 * read as terminals. Pure CSS + one local state; renders once, fetches nothing.
 */
function CodePanel({ title, tabs }: { title: string; tabs: CodeTab[] }) {
  const { t } = useTranslation("devices")
  const [active, setActive] = useState(0)
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    await navigator.clipboard.writeText(tabs[active].code)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }

  return (
    <div className="overflow-hidden rounded-lg border border-zinc-800 bg-zinc-950">
      <div className="flex items-center gap-1 border-b border-zinc-800 bg-zinc-900/80 py-1 pl-3 pr-1">
        <span className="mr-2 text-[11px] font-medium uppercase tracking-wide text-zinc-400">
          {title}
        </span>
        {tabs.length > 1 &&
          tabs.map((tab, i) => (
            <button
              key={tab.label}
              type="button"
              onClick={() => {
                setActive(i)
                setCopied(false)
              }}
              className={cn(
                "rounded-md px-2 py-1 font-mono text-[11px] transition-colors",
                i === active
                  ? "bg-zinc-700/60 text-zinc-50"
                  : "text-zinc-400 hover:text-zinc-200",
              )}
              aria-pressed={i === active}
            >
              {tab.label}
            </button>
          ))}
        {tabs.length === 1 && (
          <span className="font-mono text-[11px] text-zinc-500">{tabs[0].label}</span>
        )}
        <button
          type="button"
          onClick={copy}
          title={t("api.copy")}
          aria-label={t("api.copy")}
          className="ml-auto inline-flex min-h-7 items-center gap-1.5 rounded-md px-2 text-xs text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-zinc-100"
        >
          {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
          {copied ? t("api.copied") : t("api.copy")}
        </button>
      </div>
      <pre className="overflow-x-auto p-3 font-mono text-xs leading-relaxed text-zinc-100">
        {tabs[active].code}
      </pre>
    </div>
  )
}

function MethodChip({ method }: { method: "GET" | "POST" }) {
  return (
    <Badge
      variant="outline"
      className={cn(
        "shrink-0 font-mono text-[11px]",
        method === "GET"
          ? "border-sky-500/40 bg-sky-500/10 text-sky-600 dark:text-sky-400"
          : "border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
      )}
    >
      {method}
    </Badge>
  )
}

function EndpointLine({ method, path }: { method: "GET" | "POST"; path: string }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <MethodChip method={method} />
      <code className="break-all font-mono text-xs text-muted-foreground">{path}</code>
    </div>
  )
}

/**
 * The public share-link controls (admin panel only, devices.manage): one
 * random-URL copy of this exact guide lives at /device-docs/{token}. New link
 * kills the old one instantly; Disable kills sharing altogether. The token
 * unlocks the docs page and nothing else.
 */
function ShareCard() {
  const { t } = useTranslation("devices")
  const { t: tc } = useTranslation("common")
  const [token, setToken] = useState<string | null | undefined>(undefined)
  const [busy, setBusy] = useState<"rotate" | "revoke" | null>(null)
  const [copied, setCopied] = useState(false)
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: { token: string | null } }>("/device-docs-share")
      .then((res) => !cancelled && setToken(res.data.token))
      .catch(() => !cancelled && setToken(null))
    return () => {
      cancelled = true
    }
  }, [])

  const url =
    token && typeof window !== "undefined"
      ? `${window.location.origin}/device-docs/${token}`
      : null

  async function rotate() {
    setBusy("rotate")
    try {
      const res = await apiFetch<{ data: { token: string } }>("/device-docs-share/rotate", {
        method: "POST",
      })
      setToken(res.data.token)
      setCopied(false)
      toast.success(t("api.share.rotated"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(null)
    }
  }

  function revoke() {
    confirmDelete(async () => {
      setBusy("revoke")
      try {
        await apiFetch("/device-docs-share", { method: "DELETE" })
        setToken(null)
        toast.success(t("api.share.disabledDone"))
      } catch (error) {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      } finally {
        setBusy(null)
      }
    }, t("api.share.disableWarning"))
  }

  async function copyUrl() {
    if (!url) return
    await navigator.clipboard.writeText(url)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }

  return (
    <section className="space-y-3 rounded-xl border bg-card p-4 sm:p-5">
      <div className="flex flex-wrap items-center gap-2">
        <Link2 className="size-4 text-primary" />
        <h3 className="text-sm font-semibold">{t("api.share.title")}</h3>
        <Badge
          variant="outline"
          className={cn(
            "text-[11px]",
            token
              ? "border-success/40 bg-success/10 text-success"
              : "text-muted-foreground",
          )}
        >
          {token ? t("api.share.active") : t("api.share.off")}
        </Badge>
      </div>
      <p className="text-sm text-muted-foreground">{t("api.share.hint")}</p>

      {token === undefined ? null : token ? (
        <div className="space-y-3">
          <div className="flex items-center gap-2 overflow-hidden rounded-lg border bg-muted/40 py-1 pl-3 pr-1">
            <code className="min-w-0 flex-1 truncate font-mono text-xs">{url}</code>
            <Button
              variant="ghost"
              size="sm"
              className="h-7 shrink-0 gap-1.5 px-2 text-xs"
              onClick={copyUrl}
            >
              {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
              {copied ? t("api.copied") : t("api.copy")}
            </Button>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={rotate}
              loading={busy === "rotate"}
              disabled={busy === "revoke"}
            >
              <RefreshCw className="size-3.5" /> {t("api.share.rotate")}
            </Button>
            <Button
              variant="outline"
              size="sm"
              className="text-destructive hover:text-destructive"
              onClick={revoke}
              loading={busy === "revoke"}
              disabled={busy === "rotate"}
            >
              <ShieldOff className="size-3.5" /> {t("api.share.disable")}
            </Button>
          </div>
        </div>
      ) : (
        <Button size="sm" onClick={rotate} loading={busy === "rotate"}>
          <Link2 className="size-3.5" /> {t("api.share.generate")}
        </Button>
      )}
      {confirmDialog}
    </section>
  )
}

const STEP_IDS = ["provision", "heartbeat", "roster", "gate", "upload", "recover"] as const
type StepId = (typeof STEP_IDS)[number]

/** One node on the integration timeline: number bubble, cadence chip, card. */
function Step({
  index,
  id,
  last,
  children,
}: {
  index: number
  id: StepId
  last?: boolean
  children: ReactNode
}) {
  const { t } = useTranslation("devices")

  return (
    <li id={`device-api-${id}`} className="flex scroll-mt-28 gap-3 sm:gap-4">
      <div className="flex flex-col items-center" aria-hidden>
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-sm font-semibold text-primary">
          {index}
        </span>
        {!last && <span className="mt-1 w-px flex-1 bg-border" />}
      </div>
      <div className="min-w-0 flex-1 space-y-3 rounded-xl border bg-card p-4 pb-5 sm:p-5">
        <div className="space-y-1.5">
          <span className="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
            {t(`api.steps.${id}.when`)}
          </span>
          <h3 className="text-sm font-semibold">{t(`api.steps.${id}.title`)}</h3>
        </div>
        {children}
      </div>
    </li>
  )
}

/**
 * The integration handbook for whoever flashes a terminal (LILYGO
 * T-SIM7670G-S3, Temari's own build; F08 NFC cards): one guided flow —
 * provision → heartbeat → roster → gate → upload → recovery — with cURL and
 * ESP32/Arduino samples per endpoint against the live base URL. Every cadence
 * is exact by design: firmware follows instructions, it doesn't decide.
 * Rendered in two places: the platform panel tab (manageShare — with the
 * public share-link controls) and the public /device-docs/{token} page
 * (read-only, exact same content). Static apart from the share card.
 */
export function ApiDocsTab({ manageShare = false }: { manageShare?: boolean }) {
  const { t } = useTranslation("devices")

  const authHeader = `Authorization: Bearer tmd_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX`

  const serialProvision = `# USB-C serial console, 115200 baud
> provision api_url ${API_URL}
> provision token tmd_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
> provision save
OK — saved to NVS. Rebooting…`

  const esp32Provision = `#include <Preferences.h>

Preferences prefs;
String apiUrl, token;

// Called by the provisioning console — writes to NVS, which survives
// reboots AND OTA firmware updates (OTA rewrites app partitions only).
void saveConfig(const String& url, const String& tok) {
  prefs.begin("temari", false);
  prefs.putString("api_url", url);
  prefs.putString("token", tok);
  prefs.end();
}

void loadConfig() {              // call in setup()
  prefs.begin("temari", true);
  apiUrl = prefs.getString("api_url");
  token  = prefs.getString("token");
  prefs.end();
}`

  const curlHeartbeat = `curl -X POST "${API_URL}/device/heartbeat" \\
  -H "Authorization: Bearer tmd_XXXXXXXX" \\
  -H "Accept: application/json"`

  const esp32Heartbeat = `#define TINY_GSM_MODEM_SIM7600      // the SIM7670G speaks the SIM7600 AT set
#include <TinyGsmClient.h>
#include <ArduinoHttpClient.h>
#include <ArduinoJson.h>

TinyGsm            modem(Serial1); // SIM7670G on UART1
TinyGsmClientSecure net(modem);
HttpClient         http(net, HOST, 443);   // HOST parsed from NVS api_url

String rosterVersion;              // last synced meta.version (also in NVS)

void heartbeat() {                 // on boot, then every 5 minutes
  http.beginRequest();
  http.post("/api/v1/device/heartbeat");
  http.sendHeader("Authorization", "Bearer " + token);
  http.sendHeader("Accept", "application/json");
  http.endRequest();
  if (http.responseStatusCode() != 200) { http.stop(); return; }

  JsonDocument doc;
  deserializeJson(doc, http.responseBody());
  http.stop();

  syncClock(doc["data"]["server_time"]);     // kill clock drift every beat

  const char* v = doc["data"]["roster_version"];
  if (rosterVersion != v) pullRoster();      // step 3 — immediately
}`

  const curlRoster = `curl "${API_URL}/device/roster" \\
  -H "Authorization: Bearer tmd_XXXXXXXX" \\
  -H "Accept: application/json" \\
  --compressed`

  const esp32Roster = `void pullRoster() {
  http.beginRequest();
  http.get("/api/v1/device/roster");
  http.sendHeader("Authorization", "Bearer " + token);
  http.endRequest();
  if (http.responseStatusCode() != 200) { http.stop(); return; }

  JsonDocument doc;
  deserializeJson(doc, http.responseBody());
  http.stop();

  beginRosterWrite();              // rewrite the sorted UID table in flash
  for (JsonVariant uid : doc["data"]["students"].as<JsonArray>())
    addCard(uid.as<const char*>(), STUDENT);
  for (JsonVariant uid : doc["data"]["employees"].as<JsonArray>())
    addCard(uid.as<const char*>(), EMPLOYEE);
  commitRosterWrite();

  rosterVersion = doc["meta"]["version"].as<String>();
  persistRosterVersion(rosterVersion);       // NVS — survives reboot
}`

  const rosterResponse = `{
  "data": {
    "students":  ["04A1B2C3", "1B9E4F27"],
    "employees": ["7C33D8A1"]
  },
  "meta": {
    "students": 2,
    "employees": 1,
    "version": "9f2c4a08…64-char sha-256…",
    "generated_at": "2026-07-25T03:00:12+00:00"
  }
}`

  const heartbeatResponse = `{
  "data": {
    "name": "Main gate",
    "audience": "both",
    "server_time": "2026-07-25T08:12:44+00:00",
    "roster_version": "9f2c4a08…64-char sha-256…"
  }
}`

  const esp32Gate = `// F08 cards carry a 4-byte NUID → 8 uppercase hex characters.
String uidHex(const uint8_t* uid, uint8_t len) {
  String s;
  for (uint8_t i = 0; i < len; i++) {
    if (uid[i] < 0x10) s += '0';
    s += String(uid[i], HEX);
  }
  s.toUpperCase();
  return s;                        // "04A1B2C3"
}

void onCardTap(const uint8_t* uid, uint8_t len) {
  String hex = uidHex(uid, len);
  bool ok = rosterContains(hex);   // binary search over the sorted table
  showResult(ok);                  // green or red — instantly, no network
  queueScan(hex, isoNow());        // queue EVERY tap, accepted or rejected
}`

  const curlEvents = `curl -X POST "${API_URL}/device/events" \\
  -H "Authorization: Bearer tmd_XXXXXXXX" \\
  -H "Content-Type: application/json" \\
  -d '{
    "events": [
      { "uid": "04A1B2C3", "scanned_at": "2026-07-25T08:02:11+03:00" },
      { "uid": "7C33D8A1", "scanned_at": "2026-07-25T08:03:45+03:00" }
    ]
  }'`

  const esp32Events = `void flushQueue() {                // every 5 minutes
  String body = buildBatchJson(500);        // oldest 500 queued scans
  if (body.isEmpty()) return;

  http.beginRequest();
  http.post("/api/v1/device/events");
  http.sendHeader("Authorization", "Bearer " + token);
  http.sendHeader("Content-Type", "application/json");
  http.sendHeader("Content-Length", body.length());
  http.beginBody();
  http.print(body);
  http.endRequest();

  int status = http.responseStatusCode();
  http.stop();

  if (status == 200) dequeue(500); // delete ONLY after the 200 ack
  // any other status: keep the queue untouched, retry in 5 minutes
}`

  const eventsResponse = `{
  "data": { "accepted": 2, "duplicates": 0 },
  "message": "Events received."
}`

  const scrollTo = (id: StepId) =>
    document.getElementById(`device-api-${id}`)?.scrollIntoView({ behavior: "smooth" })

  return (
    <div className="page-gutter space-y-4 pb-8">
      <section className="space-y-3 rounded-xl border bg-card p-4 sm:p-5">
        <h2 className="text-base font-semibold">{t("api.title")}</h2>
        <p className="text-sm text-muted-foreground">{t("api.intro")}</p>
        <CodePanel title={t("api.baseUrl")} tabs={[{ label: "URL", code: API_URL }]} />
        <CodePanel
          title={t("api.tokenHeader")}
          tabs={[{ label: "HTTP", code: authHeader }]}
        />
        <p className="text-xs text-muted-foreground">{t("api.authNote")}</p>
      </section>

      {manageShare && <ShareCard />}

      <nav
        className="scrollbar-none sticky top-0 z-10 -mx-1 flex gap-1.5 overflow-x-auto bg-background/85 px-1 py-2 backdrop-blur"
        aria-label={t("api.title")}
      >
        {STEP_IDS.map((id, i) => (
          <button
            key={id}
            type="button"
            onClick={() => scrollTo(id)}
            className="pressable inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted"
          >
            <span className="font-semibold text-primary">{i + 1}</span>
            {t(`api.steps.${id}.title`)}
          </button>
        ))}
      </nav>

      <ol className="space-y-4">
        <Step index={1} id="provision">
          <p className="text-sm text-muted-foreground">{t("api.steps.provision.body")}</p>
          <CodePanel
            title={t("api.example")}
            tabs={[
              { label: "Serial", code: serialProvision },
              { label: "ESP32", code: esp32Provision },
            ]}
          />
          <p className="text-xs text-muted-foreground">{t("api.steps.provision.note")}</p>
        </Step>

        <Step index={2} id="heartbeat">
          <EndpointLine method="POST" path={`${API_URL}/device/heartbeat`} />
          <p className="text-sm text-muted-foreground">{t("api.steps.heartbeat.body")}</p>
          <CodePanel
            title={t("api.request")}
            tabs={[
              { label: "cURL", code: curlHeartbeat },
              { label: "ESP32", code: esp32Heartbeat },
            ]}
          />
          <CodePanel
            title={`${t("api.response")} · 200`}
            tabs={[{ label: "JSON", code: heartbeatResponse }]}
          />
          <p className="text-xs text-muted-foreground">{t("api.steps.heartbeat.note")}</p>
        </Step>

        <Step index={3} id="roster">
          <EndpointLine method="GET" path={`${API_URL}/device/roster`} />
          <p className="text-sm text-muted-foreground">{t("api.steps.roster.body")}</p>
          <CodePanel
            title={t("api.request")}
            tabs={[
              { label: "cURL", code: curlRoster },
              { label: "ESP32", code: esp32Roster },
            ]}
          />
          <CodePanel
            title={`${t("api.response")} · 200`}
            tabs={[{ label: "JSON", code: rosterResponse }]}
          />
          <p className="text-xs text-muted-foreground">{t("api.steps.roster.note")}</p>
        </Step>

        <Step index={4} id="gate">
          <p className="text-sm text-muted-foreground">{t("api.steps.gate.body")}</p>
          <CodePanel title={t("api.example")} tabs={[{ label: "ESP32", code: esp32Gate }]} />
          <p className="text-xs text-muted-foreground">{t("api.steps.gate.note")}</p>
        </Step>

        <Step index={5} id="upload">
          <EndpointLine method="POST" path={`${API_URL}/device/events`} />
          <p className="text-sm text-muted-foreground">{t("api.steps.upload.body")}</p>
          <CodePanel
            title={t("api.request")}
            tabs={[
              { label: "cURL", code: curlEvents },
              { label: "ESP32", code: esp32Events },
            ]}
          />
          <CodePanel
            title={`${t("api.response")} · 200`}
            tabs={[{ label: "JSON", code: eventsResponse }]}
          />
          <p className="text-xs text-muted-foreground">{t("api.steps.upload.note")}</p>
        </Step>

        <Step index={6} id="recover" last>
          <ul className="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
            <li>{t("api.steps.recover.e401")}</li>
            <li>{t("api.steps.recover.e422")}</li>
            <li>{t("api.steps.recover.queue")}</li>
          </ul>
        </Step>
      </ol>

      <section className="space-y-3 rounded-xl border border-primary/20 bg-primary/[0.04] p-4 sm:p-5">
        <h3 className="text-sm font-semibold">{t("api.storage.title")}</h3>
        <ul className="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
          <li>{t("api.storage.t1")}</li>
          <li>{t("api.storage.t2")}</li>
          <li>{t("api.storage.t3")}</li>
        </ul>
      </section>
    </div>
  )
}
