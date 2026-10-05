type PasskeyAuthenticationOptions = PublicKeyCredentialRequestOptions & {
    challenge: string
    allowCredentials?: Array<PublicKeyCredentialDescriptor & {
        id: string
    }>
}

function base64UrlToArrayBuffer(value: string): ArrayBuffer {
    const base64 = value
        .replace(/-/g, '+')
        .replace(/_/g, '/')
        .padEnd(Math.ceil(value.length / 4) * 4, '=')

    const binary = window.atob(base64)
    const bytes = new Uint8Array(binary.length)

    for (let index = 0; index < binary.length; index++) {
        bytes[index] = binary.charCodeAt(index)
    }

    return bytes.buffer
}

function arrayBufferToBase64Url(value: ArrayBuffer): string {
    const bytes = new Uint8Array(value)
    let binary = ''

    for (const byte of bytes) {
        binary += String.fromCharCode(byte)
    }

    return window.btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/g, '')
}

function prepareAuthenticationOptions(
    options: PasskeyAuthenticationOptions,
): PublicKeyCredentialRequestOptions {
    return {
        ...options,
        challenge: base64UrlToArrayBuffer(options.challenge),
        allowCredentials: options.allowCredentials?.map(credential => ({
            ...credential,
            id: base64UrlToArrayBuffer(credential.id),
        })),
    }
}

export async function authenticateWithPasskey(): Promise<void> {
    if (!window.PublicKeyCredential || !navigator.credentials) {
        throw new Error('Passkeys are not supported by this browser.')
    }

    const optionsResponse = await fetch(route('passkey.login-options'), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })

    if (!optionsResponse.ok) {
        throw new Error('Unable to start passkey authentication.')
    }

    const options = await optionsResponse.json()

    const credential = await navigator.credentials.get({
        publicKey: prepareAuthenticationOptions(options),
    })

    if (!(credential instanceof PublicKeyCredential)) {
        throw new Error('Passkey authentication was cancelled.')
    }

    const response = credential.response

    if (!(response instanceof AuthenticatorAssertionResponse)) {
        throw new Error('The passkey response was invalid.')
    }

    const loginResponse = await fetch(route('passkey.login'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({
            id: credential.id,
            rawId: arrayBufferToBase64Url(credential.rawId),
            type: credential.type,
            response: {
                authenticatorData: arrayBufferToBase64Url(response.authenticatorData),
                clientDataJSON: arrayBufferToBase64Url(response.clientDataJSON),
                signature: arrayBufferToBase64Url(response.signature),
                userHandle: response.userHandle
                    ? arrayBufferToBase64Url(response.userHandle)
                    : null,
            },
            clientExtensionResults: credential.getClientExtensionResults(),
        }),
    })

    if (!loginResponse.ok) {
        const data = await loginResponse.json().catch(() => null)

        throw new Error(
            data?.message ?? 'Passkey authentication failed.',
        )
    }

    window.location.href = route('dashboard')
}