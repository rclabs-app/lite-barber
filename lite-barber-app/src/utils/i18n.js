const translations = {
  'pt-BR': {
    since: 'DESDE 2024',
    rating: 'Avaliação',
    experience: 'Experiência',
    clients: 'Clientes',
    bookNow: 'AGENDAR AGORA',
    digitalQueue: 'FILA DIGITAL',
    login: 'LOGIN',
    bookingTitle: 'Agendamento',
    personalData: 'DADOS PESSOAIS',
    fullNamePlaceholder: 'Seu nome completo',
    specialist: 'ESPECIALISTA',
    exclusiveServices: 'SERVIÇOS EXCLUSIVOS',
    availability: 'DISPONIBILIDADE',
    additionalNotes: 'NOTAS ADICIONAIS',
    notesPlaceholder: 'Alguma preferência especial?',
    confirmBooking: 'RESERVAR HORÁRIO',
    bookingInProgress: 'RESERVANDO...',
  },
  'pt-PT': {
    since: 'DESDE 2024',
    rating: 'Avaliação',
    experience: 'Experiência',
    clients: 'Clientes',
    bookNow: 'MARCAR AGORA',
    digitalQueue: 'FILA DIGITAL',
    login: 'ENTRAR',
    bookingTitle: 'Marcação',
    personalData: 'DADOS PESSOAIS',
    fullNamePlaceholder: 'O seu nome completo',
    specialist: 'PROFISSIONAL',
    exclusiveServices: 'SERVIÇOS EXCLUSIVOS',
    availability: 'DISPONIBILIDADE',
    additionalNotes: 'NOTAS ADICIONAIS',
    notesPlaceholder: 'Alguma preferência especial?',
    confirmBooking: 'CONFIRMAR MARCAÇÃO',
    bookingInProgress: 'A RESERVAR...',
  },
  'en-US': {
    since: 'EST. 2024',
    rating: 'Rating',
    experience: 'Experience',
    clients: 'Clients',
    bookNow: 'BOOK NOW',
    digitalQueue: 'WALK-IN QUEUE',
    login: 'LOGIN',
    bookingTitle: 'Booking',
    personalData: 'YOUR DETAILS',
    fullNamePlaceholder: 'Your full name',
    specialist: 'YOUR BARBER',
    exclusiveServices: 'OUR SERVICES',
    availability: 'AVAILABILITY',
    additionalNotes: 'ADDITIONAL NOTES',
    notesPlaceholder: 'Any special request?',
    confirmBooking: 'BOOK APPOINTMENT',
    bookingInProgress: 'BOOKING...',
  },
}

export function t(locale, key) {
  const dict = translations[locale] || translations['pt-BR']
  return dict[key] || translations['pt-BR'][key] || key
}

export function formatCurrency(value, currency = 'BRL', locale = 'pt-BR') {
  const num = Number(value || 0)
  try {
    return new Intl.NumberFormat(locale || 'pt-BR', {
      style: 'currency',
      currency: currency || 'BRL',
    }).format(num)
  } catch {
    return `R$ ${num.toFixed(2)}`
  }
}
