export const getErrorMessage = ( error: unknown ): string =>
	error ? (error as Error).message : ''
