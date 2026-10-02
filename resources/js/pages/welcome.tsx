import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { login, register, village } from '@/routes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center justify-center gap-8 bg-background p-6 text-center text-foreground">
                <div className="space-y-3">
                    <p className="text-sm tracking-widest text-muted-foreground uppercase">
                        Private server
                    </p>
                    <h1 className="text-4xl font-bold sm:text-5xl">{name}</h1>
                    <p className="mx-auto max-w-md text-muted-foreground">
                        Build your village, train your ninja and battle
                        alongside your friends.
                    </p>
                </div>

                <nav className="flex gap-3">
                    {auth.user ? (
                        <Button asChild>
                            <Link href={village()}>Enter the village</Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild variant="outline">
                                <Link href={login()}>Log in</Link>
                            </Button>
                            <Button asChild>
                                <Link href={register()}>Sign up</Link>
                            </Button>
                        </>
                    )}
                </nav>
            </div>
        </>
    );
}
