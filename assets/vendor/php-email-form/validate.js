document.addEventListener('DOMContentLoaded', function () {
  const forms = document.querySelectorAll('.php-email-form');

  forms.forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      const action = form.getAttribute('action');
      const formData = new FormData(form);
      const loading = form.querySelector('.loading');
      const error = form.querySelector('.error-message');
      const success = form.querySelector('.sent-message');

      if (loading) loading.style.display = 'block';
      if (error) {
        error.innerHTML = '';
        error.style.display = 'none';
      }
      if (success) success.style.display = 'none';

      fetch(action, {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json'
        }
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (loading) loading.style.display = 'none';
          if (data.status === 'success') {
            if (success) success.style.display = 'block';
            form.reset();
          } else {
            if (error) {
              error.innerHTML = data.message || 'An error occurred while sending the form.';
              error.style.display = 'block';
            }
          }
        })
        .catch(function () {
          if (loading) loading.style.display = 'none';
          if (error) {
            error.innerHTML = 'Unable to send form. Please try again later.';
            error.style.display = 'block';
          }
        });
    });
  });
});
