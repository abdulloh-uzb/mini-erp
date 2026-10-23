import { useState } from 'react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { formatPhone } from '@/lib/phone';

type Props = Omit<ComponentProps<typeof Input>, 'type' | 'value' | 'onChange'>;

export default function PhoneInput({ defaultValue, ...props }: Props) {
    const [value, setValue] = useState(() =>
        formatPhone(String(defaultValue ?? '')),
    );

    return (
        <Input
            type="tel"
            inputMode="numeric"
            autoComplete="tel"
            placeholder="+998 __ ___ __ __"
            {...props}
            value={value}
            onChange={(event) => setValue(formatPhone(event.target.value))}
        />
    );
}
