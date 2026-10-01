{{--
    Widget chat mengambang (FR-3). Di-include dari layouts/app.blade.php
    untuk semua halaman KECUALI home (yang punya "Ask Pilmy" inline sendiri
    -- lihat $hideChatWidget di film.home). Backend sama persis:
    /chatbot/send dan /chatbot/reset, cuma UI-nya beda bentuk.
--}}
<button id="chatbot-toggle" class="btn btn-sun rounded-circle shadow"
        style="position:fixed; bottom:20px; right:20px; width:56px; height:56px; z-index:1050;">
    💬
</button>

<div id="chatbot-panel" class="card shadow"
     style="display:none; flex-direction:column; position:fixed; bottom:85px; right:20px; width:320px; max-height:450px; z-index:1050; background:#12224d; border-color: rgba(255,255,255,0.15);">
    <div class="card-header text-white d-flex justify-content-between align-items-center py-2" style="background: var(--ocean);">
        <span>🎬 Ask Pilmy</span>
        <button id="chatbot-close" type="button" class="btn-close btn-close-white btn-sm" aria-label="Tutup"></button>
    </div>
    <div id="chatbot-messages" class="card-body d-flex flex-column gap-2" style="overflow-y:auto; height:300px; font-size:0.9rem;">
        <div class="chat-bubble-bot align-self-start">Tanya apa saja soal film &mdash; sutradara, rekomendasi film mirip, dsb.</div>
    </div>
    <div class="card-footer p-2" style="border-color: rgba(255,255,255,0.1);">
        <form id="chatbot-form" class="d-flex gap-1">
            <input type="text" id="chatbot-input" class="form-control form-control-sm search-input"
                   placeholder="Ketik pesan..." autocomplete="off" maxlength="1000">
            <button type="submit" class="btn btn-sun btn-sm">Kirim</button>
        </form>
        <small id="chatbot-status" class="text-white-50"></small>
    </div>
</div>

<script>
(function () {
    const toggle = document.getElementById('chatbot-toggle');
    const panel = document.getElementById('chatbot-panel');
    const closeBtn = document.getElementById('chatbot-close');
    const form = document.getElementById('chatbot-form');
    const input = document.getElementById('chatbot-input');
    const messages = document.getElementById('chatbot-messages');
    const status = document.getElementById('chatbot-status');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    toggle.addEventListener('click', () => {
        panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
    });
    closeBtn.addEventListener('click', () => { panel.style.display = 'none'; });

    function appendMessage(role, text) {
        const bubble = document.createElement('div');
        bubble.className = role === 'user' ? 'chat-bubble-user align-self-end' : 'chat-bubble-bot align-self-start';
        bubble.textContent = text; // textContent, bukan innerHTML -- cegah XSS
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const message = input.value.trim();
        if (!message) return;

        appendMessage('user', message);
        input.value = '';
        status.textContent = 'Mengetik...';

        try {
            const res = await fetch('{{ route('chatbot.send') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: message }),
            });
            const data = await res.json();

            if (!res.ok) {
                appendMessage('bot', data.error || 'Terjadi kesalahan.');
                status.textContent = '';
                return;
            }

            appendMessage('bot', data.reply);
            status.textContent = data.messagesUsed + '/' + data.messagesLimit + ' pesan dipakai';
        } catch (err) {
            appendMessage('bot', 'Chatbot sedang tidak bisa dihubungi. Coba lagi.');
            status.textContent = '';
        }
    });
})();
</script>
