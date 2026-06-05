const toggle = document.querySelector('.nav-toggle');
const links = document.querySelector('.nav-links');

if (toggle && links) {
  toggle.addEventListener('click', () => {
    links.classList.toggle('open');
    toggle.classList.toggle('open');
  });
}

document.querySelectorAll('.nav-links a').forEach((link) => {
  link.addEventListener('click', () => {
    links?.classList.remove('open');
    toggle?.classList.remove('open');
  });
});

const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
      }
    });
  },
  { threshold: 0.14 }
);

document.querySelectorAll('.reveal').forEach((item) => observer.observe(item));

const dateInput = document.querySelector('input[name="appointment_date"]');
if (dateInput) {
  dateInput.min = new Date().toISOString().split('T')[0];
}

const timeSelect = document.querySelector('select[name="appointment_time"]');

async function loadAvailableSlots() {
  if (!dateInput || !timeSelect || !dateInput.value) {
    return;
  }

  timeSelect.innerHTML = '<option value="">Loading...</option>';

  try {
    const url = `${timeSelect.dataset.slotsUrl}?date=${encodeURIComponent(dateInput.value)}`;
    const response = await fetch(url);
    const data = await response.json();
    const slots = Array.isArray(data.slots) ? data.slots : [];

    if (slots.length === 0) {
      timeSelect.innerHTML = '<option value="">No times available</option>';
      return;
    }

    timeSelect.innerHTML = '<option value="">Choose time</option>';
    slots.forEach((slot) => {
      const option = document.createElement('option');
      option.value = slot;
      option.textContent = slot;
      timeSelect.appendChild(option);
    });
  } catch (error) {
    timeSelect.innerHTML = '<option value="">Could not load times</option>';
  }
}

if (dateInput && timeSelect) {
  dateInput.addEventListener('change', loadAvailableSlots);
  if (dateInput.value) {
    loadAvailableSlots();
  }
}
