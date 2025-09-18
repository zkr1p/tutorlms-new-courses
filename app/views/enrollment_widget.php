<script>

// Estilos CSS permanecen igual
const estilos = `
<style>
    #tutorlms-emails_for_enrollment {
        margin-top: 24px;
    }

    #tutorlms-emails_for_enrollment .widget-container {
        width: 100%;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 8px;
    }

    #tutorlms-emails_for_enrollment .title-label {
        display: block;
        color: #333;
        font-size: 14px;
        margin-bottom: 8px;
        font-weight: 500;
    }

    #tutorlms-emails_for_enrollment textarea {
        width: 100%;
        min-height: 100px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        color: #333;
        padding: 8px;
        margin-bottom: 8px;
        resize: vertical;
    }

    .css-9wrpi2 {
        display: flex;
        justify-content: flex-end;
    }

    .css-uyiccs {
        background: #0066cc;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 6px 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 14px;
    }

    .css-uyiccs:hover {
        background: #0052a3;
    }

    .css-12x0oi7 {
        width: 14px;
        height: 14px;
    }
</style>
`;

// Modificar el HTML del widget para incluir un ID en el textarea
const nuevoContenido = `
${estilos}
<div id="tutorlms-emails_for_enrollment">
    <label class="title-label">Enrollment E-mails</label>
    <div class="widget-container">
        <textarea id="enrollment-emails-textarea"></textarea>
        <div class="css-9wrpi2">
            <button type="button" class="css-uyiccs" id="save-enrollment-emails">
                Guardar
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="css-12x0oi7">
                    <path d="m8.25 4.5 7.5 7.5-7.5 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></path>
                </svg>
            </button>
        </div>
    </div>
</div>
`;

document.addEventListener("DOMContentLoaded", () => {
    // Función para verificar la URL y el hash
    function checkUrlAndHash() {
        const urlMatch = window.location.href.includes('/wp-admin/admin.php?page=create-course&course_id=');
        const hashMatch = window.location.hash === '#/basics';
        return urlMatch && hashMatch;
    }

    // Agregar funciones para manejar los emails
    function getCourseId() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('course_id');
    }

    async function loadEnrollmentEmails() {
        const courseId = getCourseId();
        if (!courseId) return;

        try {
            const response = await fetch(`/wp-json/tutorlms-new-courses/v1/enrollment-emails/${courseId}`, {
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                }
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            const textarea = document.getElementById('enrollment-emails-textarea');
            if (textarea && data.emails) {
                textarea.value = data.emails;
            }
        } catch (error) {
            console.error('Error loading enrollment emails:', error);
        }
    }

    async function saveEnrollmentEmails() {
        const courseId = getCourseId();
        const button = document.getElementById('save-enrollment-emails');
        const textarea = document.getElementById('enrollment-emails-textarea');

        if (!courseId || !textarea) return;

        try {
            button.disabled = true;
            button.textContent = 'Guardando...';

            const response = await fetch(`/wp-json/tutorlms-new-courses/v1/enrollment-emails/${courseId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    emails: textarea.value
                })
            });

            button.textContent = 'Guardar';
            button.disabled = false;

            if (!response.ok) throw new Error('Network response was not ok');
            
            // Mostrar mensaje de éxito
            alert('Emails guardados correctamente');
        } catch (error) {
            button.textContent = 'Guardar';
            button.disabled = false;
            console.error('Error saving enrollment emails:', error);
            alert('Error al guardar los emails');
        }
    }

    // Modificar la función insertWidget para agregar los event listeners
    function insertWidget() {
        if (!document.getElementById('tutorlms-emails_for_enrollment')) {
            const titleInput = document.querySelector('input[name="post_title"]');
            if (titleInput) {
                let targetElement = titleInput;
                for (let i = 0; i < 3; i++) {
                    if (targetElement.parentElement) {
                        targetElement = targetElement.parentElement;
                    }
                }
                targetElement.insertAdjacentHTML('afterend', nuevoContenido);
                
                // Cargar emails existentes
                loadEnrollmentEmails();
                
                // Agregar event listener al botón de guardar
                const saveButton = document.getElementById('save-enrollment-emails');
                if (saveButton) {
                    saveButton.addEventListener('click', saveEnrollmentEmails);
                }
            }
        }
    }

    // Función para manejar el widget
    function handleWidget() {
        const shouldShow = checkUrlAndHash();
        
        if (shouldShow) {
            insertWidget();
        } else {
            const widget = document.getElementById('tutorlms-emails_for_enrollment');
            if (widget) {
                widget.remove();
            }
        }
    }

    // Función para buscar botones que contengan el texto "Basics"
    function findBasicsButton(node) {
        if (node.nodeType === Node.TEXT_NODE && node.textContent.includes('Basics')) {
            return node.parentElement;
        }
        for (const child of node.childNodes) {
            const result = findBasicsButton(child);
            if (result) return result;
        }
        return null;
    }

    // Escuchar clicks en el documento
    document.addEventListener('click', (event) => {
        const clickedElement = event.target;
        if (clickedElement.tagName === 'BUTTON') {
            const button = findBasicsButton(clickedElement);
            if (button) {
                console.log('Click on Basics');
                setTimeout(handleWidget, 200);
            }
        }
    });

    // Observar cambios en el DOM
    const observer = new MutationObserver((mutations) => {
        if (checkUrlAndHash()) {
            insertWidget();
        }
    });

    // Configuración del observer
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Manejar el cambio inicial y cambios de hash
    handleWidget();
    window.addEventListener('hashchange', handleWidget);
});
</script>