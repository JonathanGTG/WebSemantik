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
     style="display:none; flex-direction:column; position:fixed; bottom:85px; right:20px; width:340px; max-height:500px; z-index:1050; background:#12224d; border-color: rgba(255,255,255,0.15);">
    <div class="card-header text-white d-flex justify-content-between align-items-center py-2" style="background: var(--ocean);">
        <span>🎬 Ask Pilmy</span>
        <button id="chatbot-close" type="button" class="btn-close btn-close-white btn-sm" aria-label="Tutup"></button>
    </div>
    <div id="chatbot-messages" class="card-body d-flex flex-column gap-2" style="overflow-y:auto; height:340px; font-size:0.9rem;">
        <div class="chat-bubble-bot align-self-start">Tanya apa saja soal film &mdash; sutradara, rekomendasi, dsb. Saya tahu 150 film di dataset CineGraph! 🎬</div>
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

<style>
.film-suggestion-cards {
    display: flex;
    gap: 8px;
    flex-wrap: nowrap;
    overflow-x: auto;
    padding: 4px 0 6px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.2) transparent;
}
.film-suggest-card {
    flex: 0 0 80px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    transition: transform .15s;
}
.film-suggest-card:hover { transform: scale(1.05); }
.film-suggest-card img {
    width: 80px;
    height: 120px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid transparent;
    transition: border-color .15s;
}
.film-suggest-card:hover img { border-color: var(--sun); }
.film-suggest-card .suggest-title {
    font-size: 0.62rem;
    text-align: center;
    line-height: 1.2;
    color: rgba(255,255,255,0.85);
    max-width: 80px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.film-no-poster {
    width: 80px;
    height: 120px;
    border-radius: 6px;
    background: rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border: 2px solid rgba(255,255,255,0.1);
}
</style>

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
    const filmDetailBase = '{{ url("/film") }}';

    toggle.addEventListener('click', () => {
        panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
        if (panel.style.display === 'flex') messages.scrollTop = messages.scrollHeight;
    });
    closeBtn.addEventListener('click', () => { panel.style.display = 'none'; });

    function appendMessage(role, text) {
        const bubble = document.createElement('div');
        bubble.className = role === 'user' ? 'chat-bubble-user align-self-end' : 'chat-bubble-bot align-self-start';
        bubble.textContent = text;
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
    }

    function appendFilmCards(films) {
        if (!films || films.length === 0) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'align-self-start w-100';

        const label = document.createElement('div');
        label.style.cssText = 'font-size:.65rem; color:rgba(255,255,255,.5); margin-bottom:4px;';
        label.textContent = '🎬 Klik poster untuk lihat detail film:';
        wrapper.appendChild(label);

        const row = document.createElement('div');
        row.className = 'film-suggestion-cards';

        films.forEach(function (film) {
            const link = document.createElement('a');
            link.href = filmDetailBase + '/' + film.film_id;
            link.className = 'film-suggest-card';
            link.title = film.title + (film.studio ? ' · ' + film.studio : '');

            if (film.poster) {
                const img = document.createElement('img');
                img.src = film.poster;
                img.alt = film.title;
                img.loading = 'lazy';
                link.appendChild(img);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'film-no-poster';
                placeholder.textContent = '🎬';
                link.appendChild(placeholder);
            }

            const title = document.createElement('div');
            title.className = 'suggest-title';
            title.textContent = film.title;
            link.appendChild(title);

            row.appendChild(link);
        });

        wrapper.appendChild(row);
        messages.appendChild(wrapper);
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
            appendFilmCards(data.filmSuggestions || []);
            status.textContent = data.messagesUsed + '/' + data.messagesLimit + ' pesan dipakai';
        } catch (err) {
            appendMessage('bot', 'Chatbot sedang tidak bisa dihubungi. Coba lagi.');
            status.textContent = '';
        }
    });
})();
</script>
