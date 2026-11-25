import { createInertiaApp, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle, Info, XCircle } from 'lucide-react';
import { ErrorBoundary } from 'react-error-boundary';
import { ErrorBoundaryFallback } from '@/components/error-boundary/error-boundary-fallback';
import { pushError } from '@/components/error-boundary/error-store';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { useDevErrorFallback } from '@/hooks/useDevErrorFallback';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PondAdminLayout from '@/layouts/pond-admin-layout';
import SettingsLayout from '@/layouts/settings/layout';

/** Fixed so a build-time environment variable can never put a wrong site name in browser tabs. */
const appName = 'Ask Upon A Pond';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            // Pages that draw their own dashboard (member or admin). Setting up two-factor and
            // confirming a password are among them, so nobody is sent out to a plain page.
            case name === 'welcome':
            case name.startsWith('pond/'):
            case name === 'settings/security':
            case name === 'auth/confirm-password':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('admin/'):
                return PondAdminLayout;
            case name.startsWith('settings/'):
                return [PondAdminLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <ErrorBoundary
                    FallbackComponent={ErrorBoundaryFallback}
                    onReset={() => router.reload()}
                    onError={(error, info) => {
                        // Push into persistent history (sessionStorage)
                        pushError(error, info.componentStack ?? null);

                        if (useDevErrorFallback()) {
                            console.error(error, info.componentStack);
                        }
                    }}
                >
                    {app}
                </ErrorBoundary>
                <Toaster
                    position="bottom-right"
                    richColors
                    closeButton
                    expand={true}
                    duration={3000}
                    icons={{
                        success: <CheckCircle className="h-4 w-4" />,
                        error: <XCircle className="h-4 w-4" />,
                        warning: <AlertTriangle className="h-4 w-4" />,
                        info: <Info className="h-4 w-4" />,
                    }}
                />
            </TooltipProvider>
        );
    },
    progress: {
        color: 'var(--primary)',
    },
});

// This will set light / dark mode on load...
initializeTheme();
