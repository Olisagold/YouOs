export const buildSignInRequiredError = () => ({
  error: 'sign_in_required',
  message: 'Sign in required to continue.',
  details: null,
  status: 401,
})

export const isSignInRequiredError = (error) => {
  if (!error || typeof error !== 'object') {
    return false
  }

  return (
    error.error === 'sign_in_required' ||
    error.error === 'unauthenticated' ||
    error.status === 401
  )
}

export default {
  buildSignInRequiredError,
  isSignInRequiredError,
}
