<?php
/**
 * Plugin Name: OeviBot GPT + ElevenLabs
 * Description: Övi 4.0.6 – mit animierter Mundbewegung während der Sprachausgabe + Sonnenbrillen-Reaktion bei Beleidigungen.
 * Version: 4.0.6
 * Author: Jeanette & Övi
 */

$api_key = defined('OEVI_OPENAI_API_KEY') && OEVI_OPENAI_API_KEY ? OEVI_OPENAI_API_KEY : '';
$eleven_api_key = defined('OEVI_ELEVEN_API_KEY') && OEVI_ELEVEN_API_KEY ? OEVI_ELEVEN_API_KEY : '';
$eleven_voice_id = 'fHhy0yYCa2scYpoTvzgV'; // Conrad

add_action('wp_footer', function () use ($api_key, $eleven_api_key, $eleven_voice_id) {
  if (!is_front_page()) return; // Övi NUR auf Startseite anzeigen

  $plugin_url = plugin_dir_url(__FILE__);
?>
<div id="oevi-avatar-container" style="position:fixed;right:20px;width:120px;height:120px;z-index:9999;bottom:90px;animation: floaty 4s ease-in-out infinite;transition: bottom 0.4s ease;">
  <img id="oevi-avatar-img" src="<?php echo $plugin_url; ?>avatar/oevinormal.png" style="width:100%;" />
</div>

<style>
@keyframes floaty {
  0%   { transform: translateY(0px); }
  50%  { transform: translateY(-8px); }
  100% { transform: translateY(0px); }
}
</style>

<div id="oevi-chat-wrapper" style="position:fixed;bottom:20px;right:20px;width:320px;background:#f4f4f8;border:1px solid #ccc;border-radius:10px;padding:10px;z-index:9998;font-family:sans-serif;display:none;flex-direction:column;">
  <div id="oevi-chat-log" style="max-height:200px;overflow-y:auto;font-size:15px;margin-bottom:10px;">
    <div><strong>Övi:</strong> Hallo! Ich bin Övi. Was möchtest du wissen?</div>
  </div>
  <textarea id="oevi-user-input" rows="2" placeholder="Deine Nachricht …" style="width:100%;font-size:14px;padding:6px;border:1px solid #aaa;border-radius:6px;"></textarea>
</div>
<button id="oevi-chatbot-button" onclick="oeviToggleChat()" style="position:fixed;bottom:20px;right:20px;background:#6b507f;color:white;padding:12px 18px;border:none;border-radius:8px;font-size:16px;z-index:9999;cursor:pointer;">Frag Övi</button>

<script>
const avatar = document.getElementById("oevi-avatar-img");
const chatbox = document.getElementById("oevi-chat-wrapper");
const chatlog = document.getElementById("oevi-chat-log");
const input = document.getElementById("oevi-user-input");

function showAvatar(name, duration = 800) {
  avatar.src = "<?php echo $plugin_url; ?>avatar/" + name;
  setTimeout(() => {
    avatar.src = "<?php echo $plugin_url; ?>avatar/oevinormal.png";
  }, duration);
}

function oeviToggleChat() {
  if (!chatbox || !avatar) return;
  const isOpen = (chatbox.style.display === 'flex');
  chatbox.style.display = isOpen ? 'none' : 'flex';

  const boxHeight = chatbox.offsetHeight || 250;
  const avatarBox = document.getElementById("oevi-avatar-container");
  avatarBox.style.bottom = isOpen ? '90px' : (boxHeight + 30) + 'px';
}

setInterval(() => {
  if (Math.random() < 0.5) showAvatar("oeviblinzellinks.png", 300);
}, 10000);

function appendMessage(sender, text) {
  if (!text) return;
  chatlog.innerHTML += `<div><strong>${sender}:</strong> ${text}</div>`;
  chatlog.scrollTop = chatlog.scrollHeight;
}

async function speakText(text) {
  try {
    const response = await fetch("https://api.elevenlabs.io/v1/text-to-speech/<?php echo esc_js($eleven_voice_id); ?>", {
      method: "POST",
      headers: {
        "xi-api-key": "<?php echo esc_js($eleven_api_key); ?>",
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        text: text,
        model_id: "eleven_multilingual_v2",
        voice_settings: { stability: 0.3, similarity_boost: 0.8 }
      })
    });
    const audioBlob = await response.blob();
    const audioUrl = URL.createObjectURL(audioBlob);
    const audio = new Audio(audioUrl);

    let talking = true;
    const talkInterval = setInterval(() => {
      if (!talking) return;
      const current = document.getElementById("oevi-avatar-img").src;
      if (current.includes("talk")) {
        showAvatar("oevinormal.png", 250);
      } else {
        showAvatar("oevitalk.png", 250);
      }
    }, 300);

    audio.addEventListener("ended", () => {
      talking = false;
      clearInterval(talkInterval);
      showAvatar("oevinormal.png");
    });

    audio.play();
    } catch (err) {
    console.error("TTS-Fehler (ElevenLabs):", err);
    appendMessage("Övi", "Oh nee, meine Stimme ist grad weg …");
    showAvatar("oevitraurig.png", 3000);
  }

}

async function handleMessage(msg) {
  const cleaned = msg.toLowerCase().trim();
  let allFaq = [], fallbacks = [], gptUsed = false;

  try {
    const listReq = await fetch("<?php echo $plugin_url; ?>faq-loader.php?file=list");
    const files = await listReq.json();
    for (const file of files) {
      if (file.endsWith(".json") && file !== "fallbacks.json") {
        const r = await fetch("<?php echo $plugin_url; ?>faq-loader.php?file=" + file);
        const data = await r.json();
        if (Array.isArray(data.faq)) allFaq.push(...data.faq);
      }
    }
  } catch (e) {
    console.error("Fehler beim Laden der FAQs:", e);
  }

  try {
    const fbRes = await fetch("<?php echo $plugin_url; ?>faq-loader.php?file=fallbacks.json");
    fallbacks = await fbRes.json();
  } catch (e) {
    console.error("Fallbacks konnten nicht geladen werden.");
  }

  const match = allFaq.find(f => cleaned === f.question || (f.keywords && f.keywords.some(k => cleaned.includes(k))));
  if (match) {
    appendMessage("Övi", match.answer);
    speakText(match.answer);

    // Sonnenbrillenmodus bei Beleidigungen
    const schlimmeWoerter = ["arschloch", "hurensohn", "nazisau", "scheiße", "fick", "wichser", "fotze", "spast", "vollhorst"];
    if (schlimmeWoerter.some(wort => cleaned.includes(wort))) {
      showAvatar("oevishade.png", 2000);
    }

    return;
  }

  try {
    const res = await fetch("https://api.openai.com/v1/chat/completions", {
      method: "POST",
      headers: {
        "Authorization": "Bearer <?php echo esc_js($api_key); ?>",
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        model: "gpt-4o",
        messages: [{ role: "user", content: msg }]
      })
    });
    const data = await res.json();
    const gptText = data.choices?.[0]?.message?.content;
if (gptText) {
  appendMessage("Övi", gptText);
  speakText(gptText);
} else {
  const fallback = fallbacks[Math.floor(Math.random() * fallbacks.length)];
  appendMessage("Övi", fallback);
  speakText(fallback);
}

    appendMessage("Övi", gptText);
    speakText(gptText);
  } catch (e) {
    appendMessage("Övi", "Da war was mit dem Denken... Versuch's später nochmal.");
  }
}

input.addEventListener("keydown", function(e) {
  if (e.key === "Enter" && !e.shiftKey) {
    e.preventDefault();
    const msg = input.value.trim();
    if (!msg) return;
    appendMessage("Du", msg);
    input.value = "";
    handleMessage(msg);
  }
});
</script>
<?php }); ?>
