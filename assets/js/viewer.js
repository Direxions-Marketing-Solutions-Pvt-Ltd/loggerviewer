document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('log-lines');
    const loader = document.getElementById('log-loader');
    const loadMoreBtn = document.getElementById('load-more');
    const statusText = document.getElementById('log-status');
    const filterBtns = document.querySelectorAll('.filter-btn');
    
    // AI Elements
    const aiModal = document.getElementById('ai-modal');
    const aiResponse = document.getElementById('ai-response');
    const aiStatus = document.getElementById('ai-status');
    const aiCachedTag = document.getElementById('ai-cached-tag');

    let currentOffset = 0;
    let currentFilter = 'all';
    let currentSearch = '';
    let isLoading = false;
    let searchTimeout = null;

    const logSearchInput = document.getElementById('log-search');

    const escapeHTML = (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    const typeWriter = (text, element, speed = 10) => {
        element.innerHTML = "";
        element.classList.add('ai-typing-cursor');
        let i = 0;
        
        // Split by lines for simpler safe rendering
        const lines = text.split('\n');
        let currentLine = 0;
        let currentChar = 0;

        const type = () => {
            if (currentLine < lines.length) {
                if (currentChar < lines[currentLine].length) {
                    // Append safe character
                    const char = lines[currentLine][currentChar];
                    const span = document.createElement('span');
                    span.textContent = char;
                    element.appendChild(span);
                    currentChar++;
                    setTimeout(type, speed);
                } else {
                    element.appendChild(document.createElement('br'));
                    currentLine++;
                    currentChar = 0;
                    setTimeout(type, speed);
                }
            } else {
                element.classList.remove('ai-typing-cursor');
            }
        };
        type();
    };

    const askAI = async (errorMessage) => {
        aiResponse.innerHTML = '<div style="display:flex; align-items:center; gap:1rem; color:var(--text-secondary);"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path></svg> Analyzing log entry with AI...</div>';
        aiStatus.style.display = 'none';
        aiCachedTag.textContent = "";
        aiModal.style.display = 'flex';

        try {
            const formData = new FormData();
            formData.append('error_message', errorMessage);
            formData.append('project_id', CONFIG.projectId);
            formData.append('csrf_token', CONFIG.csrfToken);

            const response = await fetch('index.php?action=api_ai_ask', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.analysis) {
                aiStatus.textContent = "AI ANALYZED";
                aiStatus.style.display = 'inline-block';
                aiCachedTag.textContent = data.cached ? "(from cache)" : "(new analysis)";
                
                typeWriter(data.analysis, aiResponse);
            } else if (data.error) {
                const errorSpan = document.createElement('span');
                errorSpan.style.color = 'var(--danger)';
                errorSpan.textContent = `Error: ${data.error}`;
                aiResponse.innerHTML = '';
                aiResponse.appendChild(errorSpan);
            }
        } catch (err) {
            aiResponse.innerHTML = '<span style="color:var(--danger)">Failed to get AI analysis. Please check console for details.</span>';
            console.error('AI Error:', err);
        }
    };

    window.copyAiResponse = () => {
        const text = aiResponse.innerText;
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.getElementById('copy-ai-res');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg> COPIED!';
            btn.style.color = 'var(--success)';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.color = '';
            }, 2000);
        });
    };

    // Expose to window for inline onclick
    window.askAI = askAI;

    // Helper for safe content passing
    const encodeContent = (str) => {
        try {
            return btoa(unescape(encodeURIComponent(str)));
        } catch(e) {
            return btoa(str.substring(0, 1000)); // Fallback
        }
    };

    const fetchLogs = async (offset = 0, filter = 'all', append = false) => {
        if (isLoading) return;
        isLoading = true;
        loader.hidden = false;

        try {
            const url = `index.php?action=api_logs&project_id=${CONFIG.projectId}&type=${CONFIG.type}&file=${encodeURIComponent(CONFIG.file)}&offset=${offset}&filter=${filter}&q=${encodeURIComponent(currentSearch)}`;
            const response = await fetch(url);
            const data = await response.json();

            if (!append) container.innerHTML = '';

            const highlight = (text) => {
                const escapedText = escapeHTML(text);
                if (!currentSearch) return escapedText;
                const regex = new RegExp(`(${currentSearch.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                return escapedText.replace(regex, '<mark class="highlight">$1</mark>');
            };

            const getAiBtn = (content) => {
                if (!CONFIG.aiEnabled) return '';
                const safeContent = encodeContent(content);
                return `
                    <button data-ai-content="${safeContent}" 
                            class="btn-secondary pulse-ai ask-ai-btn" 
                            style="padding: 0.25rem 0.51rem; font-size: 0.7rem; margin-left: 1rem; border-color: rgba(139, 92, 246, 0.4); color: #a78bfa; font-weight: 800;">
                        ASK AI
                    </button>
                `;
            };

            if (data.lines && data.lines.length > 0) {
                const headers = data.headers;
                const isTableMode = Object.keys(headers).length > 0;

                if (isTableMode) {
                    let tableHtml = `
                        <table class="log-table">
                            <thead>
                                <tr>
                                    ${Object.values(headers).map(h => `<th>${h}</th>`).join('')}
                                    <th style="width: 80px">AI</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    data.lines.forEach(line => {
                         const showAi = line.level === 'error' || (line.parsed && line.parsed.columns && line.parsed.columns.status >= 400);
                         
                         tableHtml += `<tr class="log-row ${line.level}">`;
                         if (line.parsed) {
                             Object.keys(headers).forEach(key => {
                                 let val = line.parsed.columns[key] || '-';
                                 let classes = 'col-' + key;
                                 
                                 // Special formatting for known keys
                                 if (key === 'status') {
                                     const status = parseInt(val);
                                     const badgeClass = status >= 500 ? 'badge-error' : (status >= 400 ? 'badge-warning' : 'badge-user');
                                     val = `<span class="badge ${badgeClass}">${val}</span>`;
                                 } else if (key === 'level') {
                                     const badgeClass = line.level === 'error' ? 'badge-error' : (line.level === 'warn' ? 'badge-warning' : 'badge-info');
                                     val = `<span class="badge ${badgeClass}">${val}</span>`;
                                 } else if (key === 'message' || key === 'content' || key === 'request') {
                                     val = `<div class="multiline-content">${highlight(val.replace(/\n/g, '<br>'))}</div>`;
                                 } else {
                                     val = highlight(val);
                                 }
                                 
                                 tableHtml += `<td class="${classes}">${val}</td>`;
                             });
                             tableHtml += `<td>${showAi ? getAiBtn(line.content) : ''}</td>`;
                         } else {
                             tableHtml += `
                                <td colspan="${Object.keys(headers).length}" class="log-line-raw ${line.level}">
                                    <div class="multiline-content">${line.content.replace(/\n/g, '<br>')}</div>
                                </td>
                                <td>${showAi ? getAiBtn(line.content) : ''}</td>
                             `;
                         }
                         tableHtml += `</tr>`;
                    });

                    tableHtml += `</tbody></table>`;
                    
                    if (append) {
                        const existingTable = container.querySelector('tbody');
                        if (existingTable) {
                             const tempDiv = document.createElement('div');
                             tempDiv.innerHTML = tableHtml;
                             const newRows = tempDiv.querySelector('tbody').innerHTML;
                             existingTable.insertAdjacentHTML('beforeend', newRows);
                        } else {
                             container.innerHTML = tableHtml;
                        }
                    } else {
                        container.innerHTML = tableHtml;
                    }

                } else {
                     if (!append) container.innerHTML = '';
                     data.lines.forEach(line => {
                        const lineEl = document.createElement('div');
                        lineEl.className = `log-line ${line.level}`;
                        lineEl.style.display = 'flex';
                        lineEl.style.justifyContent = 'space-between';
                        const showAi = line.level === 'error' || line.level === 'warn';
                        lineEl.innerHTML = `
                            <div class="multiline-content">${line.content.replace(/\n/g, '<br>')}</div>
                            ${ (showAi ? getAiBtn(line.content) : '') }
                        `;
                        container.appendChild(lineEl);
                    });
                }
                
                const totalText = data.total === -1 ? 'TOO LARGE' : data.total;
                currentOffset = data.nextOffset;
                loadMoreBtn.hidden = !data.hasMore;
                statusText.innerHTML = `
                    OFFSET: <span style="color:white">${currentOffset}</span> | 
                    TOTAL ENTRIES: <span style="color:white">${totalText}</span> | 
                    TIME: <span style="color:white">${data.stats.duration}ms</span>
                `;
            } else {
                if (!append) container.innerHTML = '<div style="padding: 2rem; text-align: center; color: #6b7280;">No logs found matching this filter.</div>';
                loadMoreBtn.hidden = true;
                statusText.innerHTML = `TOTAL ENTRIES: <span style="color:white">0</span>`;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            statusText.textContent = 'Error loading logs';
        } finally {
            isLoading = false;
            loader.hidden = true;
        }
    };

    // Button delegation for AI
    container.addEventListener('click', (e) => {
        const btn = e.target.closest('.ask-ai-btn');
        if (btn) {
            const encoded = btn.getAttribute('data-ai-content');
            try {
                const content = decodeURIComponent(escape(atob(encoded)));
                window.askAI(content);
            } catch(e) {
                window.askAI(atob(encoded));
            }
        }
    });

    fetchLogs();

    loadMoreBtn.addEventListener('click', () => {
        fetchLogs(currentOffset, currentFilter, true);
    });

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            currentOffset = 0;
            fetchLogs(0, currentFilter, false);
        });
    });

    if (logSearchInput) {
        logSearchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearch = e.target.value.trim();
                currentOffset = 0;
                fetchLogs(0, currentFilter, false);
            }, 400); // 400ms debounce
        });
    }

    const observer = new MutationObserver(() => {
        if (currentOffset <= 100) {
            container.scrollTop = container.scrollHeight;
        }
    });
    observer.observe(container, { childList: true });
});
