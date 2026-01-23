import {t} from '@lingui/macro';
import {Text} from '@mantine/core';
import {Modal} from '../../../../../../common/Modal';
import {Account} from '../../../../../../../types.ts';
import {VatSettingsForm} from './VatSettingsForm.tsx';

interface VatSettingsModalProps {
    account: Account;
    opened: boolean;
    onClose: () => void;
}

export const VatSettingsModal = ({account, opened, onClose}: VatSettingsModalProps) => {
    return (
        <Modal
            opened={opened}
            onClose={onClose}
            heading={t`VAT Registration Information`}
        >
            <Text size="sm" mb="lg" lh={1.6}>
                {t`As your business is based in the EU, we need to determine the correct VAT treatment for our platform fees:`}
            </Text>
            <div style={{
                background: 'var(--mantine-color-gray-0)',
                padding: '12px',
                borderRadius: '6px',
                marginBottom: '16px',
                border: '1px solid var(--mantine-color-gray-2)'
            }}>
                <Text size="xs" mb="xs" c="dark.6">• {t`EU VAT-registered businesses: Reverse charge mechanism applies (0% - Article 196 of VAT Directive 2006/112/EC)`}</Text>
                <Text size="xs" c="dark.6">• {t`Non-VAT registered businesses or individuals: Irish VAT at 23% applies`}</Text>
            </div>

            <VatSettingsForm
                account={account}
                onSuccess={onClose}
                showCard={false}
            />
        </Modal>
    );
};
