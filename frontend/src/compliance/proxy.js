import axios from 'axios';
import * as Sentry from '@sentry/node';
import {backendRequestHeaders} from '../ssr/backendRequestHeaders.js';
import {loggableError} from '../ssr/loggableError.js';

export const complianceHandler = async (req, res) => {
    try {
        const backendUrl = process.env.VITE_API_URL_SERVER;
        if (!backendUrl) {
            throw new Error('VITE_API_URL_SERVER environment variable is not set');
        }

        const {data: {data: backend}} = await axios.get(`${backendUrl}/public/compliance`, {
            headers: {'Accept': 'application/json', ...backendRequestHeaders(req)},
        });

        const brandingRemoved = Boolean(process.env.VITE_I_HAVE_PURCHASED_A_LICENCE);

        res.setHeader('Cache-Control', 'public, max-age=300');
        res.setHeader('X-Robots-Tag', 'noindex');
        res.status(200).json({
            support_email: backend.support_email,
            licence_status: backend.licence_status,
            licence_compliant: !brandingRemoved || backend.white_label,
        });
    } catch (error) {
        Sentry.captureException(error, {tags: {source: 'compliance-proxy'}});
        console.error('Error fetching compliance:', loggableError(error));
        res.status(502).json({message: 'Unable to reach the backend'});
    }
};
