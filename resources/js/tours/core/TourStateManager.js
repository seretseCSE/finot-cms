import axios from 'axios';

export default class TourStateManager {
    constructor() {
        this.baseUrl = '/api/product-tour';
    }

    async fetchStatus(panel = 'admin') {
        const { data } = await axios.get(`${this.baseUrl}/status`, {
            params: { panel },
        });
        return data.data;
    }

    async startTour(tourKey, panel = 'admin') {
        const { data } = await axios.post(`${this.baseUrl}/start`, {
            tour_key: tourKey,
            panel,
        });
        return data.data;
    }

    async completeTour(tourKey, metadata = {}, panel = 'admin') {
        await axios.post(`${this.baseUrl}/complete`, {
            tour_key: tourKey,
            metadata,
            panel,
        });
    }

    async skipTour(tourKey, panel = 'admin') {
        await axios.post(`${this.baseUrl}/skip`, {
            tour_key: tourKey,
            panel,
        });
    }

    async restartTour(tourKey, panel = 'admin') {
        await axios.post(`${this.baseUrl}/restart`, {
            tour_key: tourKey,
            panel,
        });
    }

    async saveProgress(tourKey, step, percentage, panel = 'admin') {
        await axios.post(`${this.baseUrl}/progress`, {
            tour_key: tourKey,
            step,
            percentage,
            panel,
        });
    }

    async fetchFeatureDiscoveries(panel = 'admin') {
        const { data } = await axios.get(`${this.baseUrl}/feature-discovery`, {
            params: { panel },
        });
        return data.data;
    }

    async dismissHint(tourKey) {
        await axios.post(`${this.baseUrl}/dismiss-hint`, {
            tour_key: tourKey,
        });
    }
}
