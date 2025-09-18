console.log('XXXXXXXXXXXXYZ');

// Estilos CSS
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

// HTML nuevo widget
const nuevoContenido = `
${estilos}
<div id="tutorlms-emails_for_enrollment">
    <label class="title-label">Enrollment E-mails</label>
    <div class="widget-container">
        <textarea>Contenido de prueba</textarea>
        <div class="css-9wrpi2">
            <button type="button" class="css-uyiccs">
                Guardar
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="css-12x0oi7">
                    <path d="m8.25 4.5 7.5 7.5-7.5 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></path>
                </svg>
            </button>
        </div>
    </div>
</div>
`;

// Función para verificar la URL y el hash
function checkUrlAndHash() {
    // Verificar la URL base
    const urlMatch = window.location.href.includes('/wp-admin/admin.php?page=create-course&course_id=');
    // Verificar el hash
    const hashMatch = window.location.hash === '#/basics';
    
    return urlMatch && hashMatch;
}

// Función principal
jQuery(document).ready(function($) {
    // Función para verificar la URL y el hash
    function checkUrlAndHash() {
        const urlMatch = window.location.href.includes('/wp-admin/admin.php?page=create-course&course_id=');
        const hashMatch = window.location.hash === '#/basics';
        return urlMatch && hashMatch;
    }

    // Función para insertar el widget
    function insertWidget() {
        if ($('#tutorlms-emails_for_enrollment').length === 0) {
            $('input[name="post_title"]')
                .parent()
                .parent()
                .parent()
                .after(nuevoContenido);
        }
    }

    // Función para manejar el widget
    function handleWidget() {
        const shouldShow = checkUrlAndHash();
        
        if (shouldShow) {
            insertWidget();
        } else {
            $('#tutorlms-emails_for_enrollment').remove();
        }
    }

    // Escuchar clicks en el botón de Basics
    $(document).on('click', 'button:contains("Basics")', function() {
        // Pequeño delay para asegurar que el DOM se ha actualizado
        console.log('Click on Basics');
        setTimeout(handleWidget, 200);
    });

    // Observar cambios en el DOM
    const observer = new MutationObserver(function(mutations) {
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
    $(window).on('hashchange', handleWidget);
});