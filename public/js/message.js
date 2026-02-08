class MessageCrypto {
    static async decrypt(encryptedData, key) {
        if (typeof CryptoJS === 'undefined') {
            throw new Error('Biblioteka CryptoJS nie została załadowana. Odśwież stronę.');
        }

        const decodedData = atob(encryptedData);
        const encryptedObj = JSON.parse(decodedData);

        const iv = CryptoJS.enc.Hex.parse(encryptedObj.iv);
        const encrypted = CryptoJS.enc.Hex.parse(encryptedObj.data);

        const keyBytes = CryptoJS.enc.Utf8.parse(key);
        const salt = CryptoJS.enc.Hex.parse(CryptoJS.SHA256(key).toString().substring(0, 32));
        const derivedKey = CryptoJS.PBKDF2(keyBytes, salt, {
            keySize: 256 / 32,
            iterations: 100000,
            hasher: CryptoJS.algo.SHA256
        });

        const decrypted = CryptoJS.AES.decrypt(
            { ciphertext: encrypted },
            derivedKey,
            {
                iv: iv,
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7
            }
        );

        const plaintext = decrypted.toString(CryptoJS.enc.Utf8);

        if (!plaintext) {
            throw new Error('Deszyfrowanie nie powiodło się - nieprawidłowy klucz?');
        }

        return plaintext;
    }
}

function getConfig() {
    const body = document.body;
    return {
        messageId: body.dataset.messageId,
        testMode: body.dataset.testMode === '1',
        burnDelay: parseInt(body.dataset.burnDelay, 10) || 50,
        maxSize: parseInt(body.dataset.maxSize, 10) || 10000,
        appUrl: body.dataset.appUrl || ''
    };
}

async function loadMessage() {
    try {
        const config = getConfig();
        const encryptionKey = window.location.hash.substring(1);

        if (!encryptionKey) {
            throw new Error('Brak klucza szyfrowania. Sprawdź czy skopiowałeś cały link.');
        }

        const currentPath = window.location.pathname;
        const pathParts = currentPath.split('/');
        const basePath = pathParts.slice(0, -2).join('/') || '';
        const url = `${basePath}/api/message/${config.messageId}${config.testMode ? '?test=1' : ''}`;

        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`API Error: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Wiadomość nie istnieje lub wygasła');
        }

        const decryptedContent = await decryptMessage(data.data.content, encryptionKey);

        showMessage(decryptedContent, data.data);

    } catch (error) {
        showError(error.message);
    }
}

async function decryptMessage(encryptedData, key) {
    return MessageCrypto.decrypt(encryptedData, key);
}

function showMessage(content, messageData) {
    document.getElementById('loadingState').classList.add('hidden');
    document.getElementById('messageContainer').classList.remove('hidden');
    
    document.getElementById('decryptedMessage').textContent = content;
    
    if (messageData.test_mode) {
        document.getElementById('testModeBadge').classList.remove('hidden');
        document.getElementById('testModeInfo').classList.remove('hidden');
        document.getElementById('messageWarning').classList.add('hidden');
    } else {
        document.getElementById('messageWarning').classList.remove('hidden');
        initializeCountdown();
    }
    
    if (messageData.test_mode && messageData.views) {
        const viewInfo = document.createElement('div');
        viewInfo.className = 'mono text-xs uppercase tracking-wider mt-4';
        viewInfo.style.color = '#999';
        viewInfo.textContent = `Wyświetlenia: ${messageData.views}`;
        document.getElementById('messageContent').appendChild(viewInfo);
    }
}

function showError(message) {
    document.getElementById('loadingState').classList.add('hidden');
    document.getElementById('errorContainer').classList.remove('hidden');
    document.getElementById('errorMessage').textContent = message;
}

let countdownInterval;

function initializeCountdown() {
    const config = getConfig();
    let burnSeconds = config.burnDelay;
    
    const countdownElement = document.getElementById('countdown');
    if (!countdownElement) {
        return;
    }
    
    countdownElement.textContent = burnSeconds;
    
    const progressBar = document.getElementById('progressBar');
    if (progressBar) {
        progressBar.style.width = '100%';
    }
    
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    
    let remainingSeconds = burnSeconds;
    
    countdownInterval = setInterval(() => {
        remainingSeconds--;
        countdownElement.textContent = remainingSeconds;
        
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            const percentage = (remainingSeconds / burnSeconds) * 100;
            progressBar.style.width = percentage + '%';
            
            progressBar.className = 'h-1 rounded-sm';
            if (percentage > 20) {
                progressBar.style.background = '#FF3B30';
            } else {
                progressBar.style.background = '#FF3B30';
                progressBar.classList.add('animate-pulse');
            }
        }
        
        if (remainingSeconds <= 3) {
            countdownElement.parentElement.classList.add('countdown-critical');
        }
        
        if (remainingSeconds <= 0) {
            clearInterval(countdownInterval);
            burnMessage();
        }
    }, 1000);
}

function burnMessage() {
    const messageContainer = document.getElementById('messageContainer');
    const destroyedContainer = document.getElementById('destroyedContainer');
    
    messageContainer.classList.add('burn-effect');
    
    setTimeout(() => {
        messageContainer.classList.add('hidden');
        destroyedContainer.classList.remove('hidden');
        destroyedContainer.classList.add('message-reveal');
    }, 3000);
}

function copyMessage() {
    const messageContent = document.getElementById('decryptedMessage').textContent;
    const copyButton = document.querySelector('button[onclick="copyMessage()"]');
    const copyIcon = copyButton.querySelector('svg');
    const originalText = copyButton.innerHTML;
    
    copyButton.innerHTML = `
        <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Kopiowanie...
    `;
    
    const showSuccess = () => {
        copyButton.innerHTML = `
            <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Treść skopiowana!
        `;
        copyButton.classList.add('copy-success');
        
        setTimeout(() => {
            copyButton.innerHTML = originalText;
            copyButton.classList.remove('copy-success');
        }, 2000);
    };
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(messageContent).then(() => {
            showSuccess();
        }).catch(() => {
            fallbackCopy();
        });
    } else {
        fallbackCopy();
    }
    
    function fallbackCopy() {
        try {
            const textarea = document.createElement('textarea');
            textarea.value = messageContent;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showSuccess();
        } catch (error) {
            copyButton.innerHTML = `
                <svg class="w-4 h-4 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Błąd kopiowania
            `;
            setTimeout(() => {
                copyButton.innerHTML = originalText;
            }, 2000);
        }
    }
}

document.addEventListener('DOMContentLoaded', loadMessage);