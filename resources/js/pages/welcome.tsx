import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { homeFor } from '@/lib/navigation';
import { login, register } from '@/routes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Xush kelibsiz" />
            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6">
                <AppLogoIcon className="size-12 fill-current text-foreground" />
                <div className="max-w-md space-y-2 text-center">
                    <h1 className="text-2xl font-semibold">{name}</h1>
                    <p className="text-muted-foreground">
                        Ichimliklar do'koni uchun ombor, hujjatlar va kassa
                        hisobi.
                    </p>
                </div>
                <div className="flex gap-3">
                    {auth.user ? (
                        <Button asChild>
                            <Link href={homeFor(auth.role)}>Bosh sahifa</Link>
                        </Button>
                    ) : (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={login()}>Kirish</Link>
                            </Button>
                            <Button asChild>
                                <Link href={register()}>Ro'yxatdan o'tish</Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
