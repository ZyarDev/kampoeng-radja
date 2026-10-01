<template>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-logo-container">
                <img
                    src="/assets/brand/kampoeng-radja-navbar.png"
                    alt="Kampoeng Radja"
                    class="auth-logo"
                />
            </div>
            <div class="auth-content">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-shell {
    min-height: 100vh;
    min-height: 100dvh;
    display: grid;
    place-items: center;
    padding: 2rem 1rem;
    background: #dff3fa url('/assets/auth/background.jpeg') center / cover no-repeat;
}

.auth-card {
    width: min(100%, 420px);
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .8);
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 18px 45px rgba(40, 86, 122, .18);
    padding: 2.25rem 2.25rem 2.5rem;
}

.auth-logo-container {
    display: flex;
    justify-content: center;
    margin: 0 auto 2.25rem;
}

.auth-logo {
    width: 190px;
    height: auto;
    object-fit: contain;
}

@media (max-width: 767px) {
    .auth-shell {
        min-height: 100dvh;
        padding: 1rem;
        background-image: url('/assets/auth/background-mobile.jpg');
        background-position: center;
        background-size: cover;
        background-repeat: no-repeat;
    }
    .auth-card { padding: 2rem 1.5rem 2.25rem; border-radius: 18px; }
    .auth-logo-container { margin-bottom: 1.75rem; }
    .auth-logo { width: 170px; }
}
</style>
