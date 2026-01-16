/**
* PHP Email Form Validation - Cleaned up to use SweetAlert2
* and handle common server errors gracefully.
*/
(function () {
  "use strict";

  let forms = document.querySelectorAll('.php-email-form');

  forms.forEach(function (e) {
    e.addEventListener('submit', function (event) {
      event.preventDefault();

      let thisForm = this;

      let action = thisForm.getAttribute('action');
      let recaptcha = thisForm.getAttribute('data-recaptcha-site-key');

      if (!action) {
        displayError(thisForm, 'The form action property is not set!');
        return;
      }

      // thisForm.querySelector('.loading').classList.add('d-block');
      let submitButton = thisForm.querySelector('button[type="submit"]');
      if (submitButton) {
        let originalButtonText = submitButton.innerHTML;
        submitButton.setAttribute('data-original-text', originalButtonText);
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
        submitButton.disabled = true;
      }

      // Hide any previous inline messages if they exist
      if (thisForm.querySelector('.error-message')) thisForm.querySelector('.error-message').classList.remove('d-block');
      if (thisForm.querySelector('.sent-message')) thisForm.querySelector('.sent-message').classList.remove('d-block');

      let formData = new FormData(thisForm);

      if (recaptcha) {
        if (typeof grecaptcha !== "undefined") {
          grecaptcha.ready(function () {
            try {
              grecaptcha.execute(recaptcha, { action: 'php_email_form_submit' })
                .then(token => {
                  formData.set('recaptcha-response', token);
                  php_email_form_submit(thisForm, action, formData);
                })
            } catch (error) {
              displayError(thisForm, error);
            }
          });
        } else {
          displayError(thisForm, 'The reCaptcha javascript API url is not loaded!')
        }
      } else {
        php_email_form_submit(thisForm, action, formData);
      }
    });
  });

  function php_email_form_submit(thisForm, action, formData) {
    fetch(action, {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(response => {
        if (response.ok) {
          return response.text();
        } else {
          // Log technical details for developer debugging
          console.error(`Server Error: ${response.status} ${response.statusText} at ${response.url}`);
          // Throw friendly error for the user
          throw new Error('Unable to send message. Please try again later.');
        }
      })
      .then(data => {
        // thisForm.querySelector('.loading').classList.remove('d-block');
        let submitButton = thisForm.querySelector('button[type="submit"]');
        if (submitButton && submitButton.getAttribute('data-original-text')) {
          submitButton.innerHTML = submitButton.getAttribute('data-original-text');
          submitButton.disabled = false;
        }
        if (data.trim() == 'OK') {
          Swal.fire({
            icon: 'success',
            title: 'Message Sent!',
            text: 'Your message has been sent. Thank you!',
            confirmButtonColor: '#0563bb'
          });
          thisForm.reset();
        } else {
          // Log the raw technical response
          console.error('Form submission returned error:', data);
          // Show generic user message
          throw new Error('We could not send your message. Please try again later.');
        }
      })
      .catch((error) => {
        displayError(thisForm, error);
      });
  }

  function displayError(thisForm, error) {
    // thisForm.querySelector('.loading').classList.remove('d-block');
    let submitButton = thisForm.querySelector('button[type="submit"]');
    if (submitButton && submitButton.getAttribute('data-original-text')) {
      submitButton.innerHTML = submitButton.getAttribute('data-original-text');
      submitButton.disabled = false;
    }

    // Log the full error to console for debugging
    console.error('Contact Form Logic Error:', error);

    let userMessage = 'Something went wrong. Please try again later.';
    if (error instanceof Error) {
      userMessage = error.message;
    }

    Swal.fire({
      icon: 'error',
      title: 'Submission Failed',
      text: userMessage,
      confirmButtonColor: '#0563bb'
    });
  }

})();
