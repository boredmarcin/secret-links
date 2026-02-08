const messageTextarea = document.getElementById('message');
const charCount = document.getElementById('charCount');

if (messageTextarea) {
    messageTextarea.addEventListener('input', function() {
        const length = this.value.length;
        const maxLength = messageTextarea.getAttribute('maxlength') || 10000;
        charCount.textContent = `${length} / ${maxLength}`;
        
        if (length > maxLength * 0.9) {
            charCount.style.color = '#FF3B30';
        } else {
            charCount.style.color = '#999';
        }
    });
}

const createForm = document.getElementById('createForm');
if (createForm) {
    createForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const message = document.getElementById('message').value.trim();
        const testModeElement = document.getElementById('testMode');
        const testMode = testModeElement ? testModeElement.checked : false;
        const customExpiry = parseInt(document.getElementById('customExpiry').value);
        const csrfToken = document.getElementById('csrf_token').value;
        
        if (!message) {
            showError('Wiadomość nie może być pusta');
            return;
        }

        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) submitBtn.disabled = true;

        showLoadingState();

        try {
            const response = await fetch('./api/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    content: message,
                    test_mode: testMode,
                    custom_expiry: customExpiry,
                    csrf_token: csrfToken
                })
            });
            
            const data = await response.json();
            
            
            if (!response.ok) {
                throw new Error(data.error || 'Wystąpił błąd podczas tworzenia wiadomości');
            }
            
            if (data.success) {
                if (data.csrf_token) {
                    document.getElementById('csrf_token').value = data.csrf_token;
                }
                showSuccessState(data.data);
            } else {
                throw new Error(data.error || 'Nie udało się utworzyć wiadomości');
            }
        } catch (error) {
            showError(error.message);
        } finally {
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) submitBtn.disabled = false;
        }
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const successState = document.getElementById('successState');
        if (successState && !successState.classList.contains('hidden')) {
            closeSuccessModal();
        }
    }
});

function hideAll() {
    ['createForm', 'loadingState', 'successState', 'errorState'].forEach(id => {
        document.getElementById(id).classList.add('hidden');
        document.getElementById(id).classList.remove('fade-in');
    });
}

function showLoadingState() {
    hideAll();
    const el = document.getElementById('loadingState');
    el.classList.remove('hidden');
    el.classList.add('fade-in');
}

function showSuccessState(data) {
    hideAll();
    const el = document.getElementById('successState');
    el.classList.remove('hidden');
    el.classList.add('fade-in');

    const linkInput = document.getElementById('generatedLink');
    linkInput.value = data.url;

    const expiryInfo = document.getElementById('expiryInfo');
    if (data.test_mode) {
        expiryInfo.textContent = 'Tryb testowy - wiadomość nie wygaśnie';
        expiryInfo.style.color = '#FF6D2E';
    } else {
        expiryInfo.textContent = `Wygasa: ${data.expires_at}`;
    }
}

function showError(message) {
    hideAll();
    const el = document.getElementById('errorState');
    el.classList.remove('hidden');
    el.classList.add('fade-in');
    document.getElementById('errorMessage').textContent = message;
}

async function copyToClipboard() {
    const linkInput = document.getElementById('generatedLink');
    const copyBtn = document.getElementById('copyBtn');
    const copyIcon = document.getElementById('copyIcon');
    const checkIcon = document.getElementById('checkIcon');
    const copyText = document.getElementById('copyText');
    
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(linkInput.value);
        } else {
            linkInput.select();
            document.execCommand('copy');
        }
        
        copyIcon.classList.add('hidden');
        checkIcon.classList.remove('hidden');
        copyText.textContent = 'Skopiowano!';
        copyBtn.classList.add('copy-success');
        
        setTimeout(() => {
            copyIcon.classList.remove('hidden');
            checkIcon.classList.add('hidden');
            copyText.textContent = 'Kopiuj';
            copyBtn.classList.remove('copy-success');
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
        linkInput.select();
        copyText.textContent = 'Zaznacz i skopiuj';
        
        setTimeout(() => {
            copyText.textContent = 'Kopiuj';
        }, 2000);
    }
}

function closeSuccessModal() {
    const success = document.getElementById('successState');
    const form = document.getElementById('createForm');
    
    const modalInner = success.querySelector('[onclick="event.stopPropagation()"]');
    if (modalInner) modalInner.classList.add('modal-exit');

    setTimeout(() => {
        success.classList.add('hidden');
        if (modalInner) modalInner.classList.remove('modal-enter', 'modal-exit');
        
        form.classList.remove('hidden');
        form.classList.add('fade-in');
    }, 200);
    
    const textarea = document.getElementById('message');
    textarea.value = '';
    if (charCount) {
        const maxLength = textarea ? textarea.getAttribute('maxlength') || 10000 : 10000;
        charCount.textContent = '0 / ' + maxLength;
        charCount.style.color = '#999';
    }
    
    const testModeCheckbox = document.getElementById('testMode');
    if (testModeCheckbox) {
        testModeCheckbox.checked = false;
    }
}

function createNewMessage() {
    closeSuccessModal();
}

function resetForm() {
    createNewMessage();
}

document.addEventListener('DOMContentLoaded', function() {
    const messageTextarea = document.getElementById('message');
    if (messageTextarea) {
        messageTextarea.focus();
    }
});