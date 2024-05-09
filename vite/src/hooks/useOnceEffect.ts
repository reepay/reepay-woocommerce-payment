import { useEffect } from 'react'

const useOnceEffect = (effect: () => void) => {
    useEffect(() => {
        effect()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])
}

export default useOnceEffect
