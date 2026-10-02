import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import CharacterSprite from '@/components/character-sprite';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { store } from '@/routes/karakter';
import { characterAssets, isFemaleAvatar } from '@/types/game';

type Props = { avatars: string[] };

const GENDERS = [
    { label: 'Pria', female: false },
    { label: 'Wanita', female: true },
];

export default function Buat({ avatars }: Props) {
    const [selected, setSelected] = useState(avatars[0]);
    const showFemale = isFemaleAvatar(selected);
    const visible = avatars.filter(
        (avatar) => isFemaleAvatar(avatar) === showFemale,
    );

    const chooseGender = (female: boolean) => {
        const first = avatars.find(
            (avatar) => isFemaleAvatar(avatar) === female,
        );

        if (first && female !== showFemale) {
            setSelected(first);
        }
    };

    return (
        <>
            <Head title="Buat Ninja" />
            <div className="grid flex-1 gap-6 p-4 lg:grid-cols-2">
                <section className="relative flex min-h-[420px] items-end justify-center overflow-hidden rounded-xl border border-sidebar-border/70 bg-gradient-to-b from-sky-200 to-amber-100 dark:border-sidebar-border dark:from-slate-800 dark:to-slate-950">
                    <img
                        src={characterAssets(selected).portrait}
                        alt="Potret ninja terpilih"
                        className="absolute inset-x-0 top-4 mx-auto h-[70%] [mask-image:linear-gradient(to_bottom,black_75%,transparent)] object-contain"
                    />
                    <CharacterSprite
                        key={selected}
                        avatar={selected}
                        className="relative z-10 h-56 w-56"
                    />
                </section>

                <section className="flex flex-col gap-6">
                    <div>
                        <h1 className="text-2xl font-semibold">Buat ninjamu</h1>
                        <p className="text-muted-foreground">
                            Pilih penampilan dan nama. Nama tidak bisa diubah
                            nanti.
                        </p>
                    </div>

                    <div
                        className="flex gap-2"
                        role="radiogroup"
                        aria-label="Jenis kelamin"
                    >
                        {GENDERS.map(({ label, female }) => (
                            <Button
                                key={label}
                                type="button"
                                role="radio"
                                aria-checked={female === showFemale}
                                variant={
                                    female === showFemale
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => chooseGender(female)}
                            >
                                {label}
                            </Button>
                        ))}
                    </div>

                    <div
                        className="grid grid-cols-5 gap-3 sm:grid-cols-9 lg:grid-cols-5 xl:grid-cols-9"
                        role="radiogroup"
                        aria-label="Avatar"
                    >
                        {visible.map((avatar) => (
                            <button
                                key={avatar}
                                type="button"
                                role="radio"
                                aria-checked={avatar === selected}
                                aria-label={`Avatar ${avatar}`}
                                onClick={() => setSelected(avatar)}
                                className={cn(
                                    'aspect-square overflow-hidden rounded-lg border-2 bg-muted transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    avatar === selected
                                        ? 'scale-105 border-primary'
                                        : 'border-transparent opacity-70 hover:opacity-100',
                                )}
                            >
                                <img
                                    src={characterAssets(avatar).face}
                                    alt=""
                                    className="size-full object-cover"
                                />
                            </button>
                        ))}
                    </div>

                    <Form
                        {...store.form()}
                        disableWhileProcessing
                        className="flex flex-col gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="avatar"
                                    value={selected}
                                />
                                <InputError message={errors.avatar} />

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nama ninja</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        minLength={3}
                                        maxLength={16}
                                        autoComplete="off"
                                        placeholder="3–16 huruf atau angka"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full sm:w-auto"
                                    data-test="create-character-button"
                                >
                                    {processing && <Spinner />}
                                    Mulai petualangan
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}
