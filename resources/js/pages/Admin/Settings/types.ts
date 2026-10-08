export type TwoFactorRequirement = 'not_required' | 'admin_only' | 'all_users'

export type SettingsPayload = {
    invitations: { 
        subject: string; 
        greeting: string; 
        message: string; 
        button_text: string; 
        footer: string 
    }
    
    ai: { 
        enabled: boolean; 
        provider: string; 
        model: string; 
        url: string; 
        has_key: boolean 
    
    }
    general: {
        company_name: string
        company_logo?: string | null
        require_2fa: TwoFactorRequirement
        default_language: string
    }

    security: {
        allow_registration: boolean
        require_email_verification: boolean
        allow_passkeys: boolean
        session_lifetime: number
        password_min_length: number
    }

    mail: {
        host?: string | null
        port?: number | null
        encryption?: 'none' | 'tls' | 'ssl' | null
        username?: string | null
        password?: string | null
        from_address?: string | null
        from_name?: string | null
    }

    captcha: {
        enabled: boolean
        provider: 'turnstile' | 'recaptcha' | 'hcaptcha'
        site_key?: string | null
        secret_key?: string | null
    }
}

export type OAuthProvider = {
    provider: 'discord' | 'google' | 'github'
    enabled: boolean
    client_id?: string | null
    client_secret?: string | null
    redirect_url?: string | null
}

export type OidcProvider = {
    id: string
    name: string
    slug: string
    enabled: boolean
    issuer: string
    client_id: string
    client_secret?: string | null
    redirect_url: string
    scopes: string[]
    allow_registration: boolean
}