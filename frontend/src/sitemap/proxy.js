import axios from 'axios';
import * as Sentry from '@sentry/node';
import {backendRequestHeaders} from '../ssr/backendRequestHeaders.js';
import {loggableError} from '../ssr/loggableError.js';

const getBackendUrl = () => {
    const backendUrl = process.env.VITE_API_URL_SERVER;
    if (!backendUrl) {
        throw new Error('VITE_API_URL_SERVER environment variable is not set');
    }
    return backendUrl;
};

const fetchSitemap = async (req, path, res, errorContext) => {
    try {
        const backendUrl = getBackendUrl();
        const response = await axios.get(`${backendUrl}/public${path}`, {
            headers: { 'Accept': 'application/xml', ...backendRequestHeaders(req) },
            responseType: 'text',
        });

        res.setHeader('Content-Type', 'application/xml');
        if (response.headers['cache-control']) {
            res.setHeader('Cache-Control', response.headers['cache-control']);
        }
        res.status(200).send(response.data);
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) {
            res.status(404).send('Sitemap not found');
            return;
        }
        Sentry.captureException(error, {
            tags: { source: 'sitemap-proxy' },
            extra: { errorContext, path },
        });
        console.error(`Error fetching ${errorContext}:`, loggableError(error));
        res.status(500).send('Internal server error');
    }
};

const validatePageParam = (page, res) => {
    if (!page || !/^\d+$/.test(page)) {
        res.status(400).send('Invalid page parameter');
        return false;
    }
    return true;
};

export const sitemapIndexHandler = async (req, res) => {
    await fetchSitemap(req, '/sitemap.xml', res, 'sitemap index');
};

export const sitemapEventsHandler = async (req, res) => {
    const { page } = req.params;
    if (!validatePageParam(page, res)) return;
    await fetchSitemap(req, `/sitemap-events-${page}.xml`, res, 'sitemap events');
};

export const sitemapOrganizersHandler = async (req, res) => {
    const { page } = req.params;
    if (!validatePageParam(page, res)) return;
    await fetchSitemap(req, `/sitemap-organizers-${page}.xml`, res, 'sitemap organizers');
};
