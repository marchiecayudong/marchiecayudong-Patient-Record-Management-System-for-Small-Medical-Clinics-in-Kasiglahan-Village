// PatientSys - small UI helpers
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('a.del-confirm').forEach(a => {
    a.addEventListener('click', e => {
      if (!confirm('Are you sure you want to delete this record?')) e.preventDefault();
    });
  });
});
