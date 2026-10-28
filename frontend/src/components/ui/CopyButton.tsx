import { useEffect, useState } from 'react';
import { Icon } from './Icon';

interface CopyButtonProps {
  value: string;
  label?: string;
  className?: string;
}

async function copyText(value: string): Promise<void> {
  if (navigator.clipboard && window.isSecureContext) {
    await navigator.clipboard.writeText(value);
    return;
  }

  const textarea = document.createElement('textarea');
  textarea.value = value;
  textarea.setAttribute('readonly', '');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  textarea.remove();
}

export function CopyButton({ value, label = 'Copy link', className = 'btn btn-secondary' }: CopyButtonProps) {
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    if (!copied) {
      return;
    }

    const timeout = window.setTimeout(() => setCopied(false), 1800);
    return () => window.clearTimeout(timeout);
  }, [copied]);

  return (
    <button
      type="button"
      className={`${className} ${copied ? 'copied' : ''}`}
      onClick={() => copyText(value).then(() => setCopied(true))}
    >
      <Icon name={copied ? 'check' : 'copy'} />
      {copied ? 'Copied' : label}
    </button>
  );
}
