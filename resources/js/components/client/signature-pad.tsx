import { Eraser } from 'lucide-react';
import { useRef, useState } from 'react';
import type { PointerEvent } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Finger/mouse signature. Submits as a PNG data URL in a hidden `name` field (empty when unsigned).
 * ponytail: plain line segments, no smoothing; swap in signature_pad if signatures look too jagged.
 */
export default function SignaturePad({ name }: { name: string }) {
    const { t } = useTranslation();
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const [dataUrl, setDataUrl] = useState('');

    const point = (event: PointerEvent<HTMLCanvasElement>) => {
        const canvas = event.currentTarget;
        const rect = canvas.getBoundingClientRect();

        return {
            x: ((event.clientX - rect.left) * canvas.width) / rect.width,
            y: ((event.clientY - rect.top) * canvas.height) / rect.height,
        };
    };

    const start = (event: PointerEvent<HTMLCanvasElement>) => {
        const ctx = event.currentTarget.getContext('2d');

        if (!ctx) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        drawing.current = true;
        const { x, y } = point(event);
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';
        ctx.beginPath();
        ctx.moveTo(x, y);
    };

    const move = (event: PointerEvent<HTMLCanvasElement>) => {
        if (!drawing.current) {
            return;
        }

        const ctx = event.currentTarget.getContext('2d');
        const { x, y } = point(event);
        ctx?.lineTo(x, y);
        ctx?.stroke();
    };

    const end = () => {
        if (drawing.current && canvasRef.current) {
            drawing.current = false;
            setDataUrl(canvasRef.current.toDataURL('image/png'));
        }
    };

    const clear = () => {
        const canvas = canvasRef.current;
        canvas?.getContext('2d')?.clearRect(0, 0, canvas.width, canvas.height);
        setDataUrl('');
    };

    return (
        <div className="space-y-1">
            <canvas
                ref={canvasRef}
                width={600}
                height={200}
                onPointerDown={start}
                onPointerMove={move}
                onPointerUp={end}
                onPointerLeave={end}
                className="h-36 w-full touch-none rounded-md border border-dashed bg-white"
                aria-label={t('Signature area: draw your signature')}
            />
            <input type="hidden" name={name} value={dataUrl} />
            <div className="flex justify-between text-xs text-muted-foreground">
                <span>{t('Sign with your finger (optional)')}</span>
                {dataUrl && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={clear}
                    >
                        <Eraser />
                        {t('Clear')}
                    </Button>
                )}
            </div>
        </div>
    );
}
