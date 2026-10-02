type Props = { line: string; portrait: string };

/** A building keeper with a speech bubble, as in the original shops. */
export default function ShopKeeper({ line, portrait }: Props) {
    return (
        <div className="flex flex-col items-center gap-3">
            <div className="relative rounded-lg border-2 border-amber-200/80 bg-amber-50 p-3 text-sm text-amber-950 shadow">
                {line}
                <span
                    aria-hidden
                    className="absolute -bottom-2 left-1/2 size-4 -translate-x-1/2 rotate-45 border-r-2 border-b-2 border-amber-200/80 bg-amber-50"
                />
            </div>
            <img
                src={portrait}
                alt="Shopkeeper"
                className="w-40 drop-shadow-lg"
            />
            <p className="text-sm font-semibold text-amber-200">Shopkeeper</p>
        </div>
    );
}
