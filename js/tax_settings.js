/**
 * Tax Settings Manager
 * Handles dynamic tax settings for customer-facing pages
 */

class TaxSettingsManager {
    constructor() {
        this.settings = {
            tax_rate: 0.06, // Default 6%
            tax_name: 'SST',
            currency_symbol: 'RM',
            currency_code: 'MYR',
            restaurant_name: 'Gourmet Delights'
        };
        this.cacheExpiry = 5 * 60 * 1000; // 5 minutes
        this.lastFetch = 0;
    }

    /**
     * Get current tax settings
     */
    async getSettings() {
        const now = Date.now();
        
        // Return cached settings if still valid
        if (now - this.lastFetch < this.cacheExpiry) {
            return this.settings;
        }

        try {
            const response = await fetch('/api/get_tax_settings.php');
            const data = await response.json();
            
            if (data.success) {
                this.settings = {
                    tax_rate: data.data.tax_rate / 100, // Convert percentage to decimal
                    tax_name: data.data.tax_name,
                    currency_symbol: data.data.currency_symbol,
                    currency_code: data.data.currency_code,
                    restaurant_name: data.data.restaurant_name
                };
                this.lastFetch = now;
            }
        } catch (error) {
            console.warn('Failed to fetch tax settings, using defaults:', error);
        }

        return this.settings;
    }

    /**
     * Calculate tax amount
     */
    calculateTax(subtotal) {
        return subtotal * this.settings.tax_rate;
    }

    /**
     * Calculate total with tax
     */
    calculateTotal(subtotal) {
        return subtotal + this.calculateTax(subtotal);
    }

    /**
     * Format currency
     */
    formatCurrency(amount) {
        return `${this.settings.currency_symbol} ${amount.toFixed(2)}`;
    }

    /**
     * Get tax label
     */
    getTaxLabel() {
        return `${this.settings.tax_name} (${(this.settings.tax_rate * 100).toFixed(1)}%)`;
    }

    /**
     * Update cart summary with current tax settings
     */
    async updateCartSummary() {
        const settings = await this.getSettings();
        
        // Update tax label
        const taxLabel = document.getElementById('tax-label');
        if (taxLabel) {
            taxLabel.textContent = this.getTaxLabel();
        }

        // Update currency symbols
        const currencyElements = document.querySelectorAll('.currency-symbol');
        currencyElements.forEach(el => {
            el.textContent = settings.currency_symbol;
        });

        // Update restaurant name
        const restaurantName = document.querySelector('.restaurant-name');
        if (restaurantName) {
            restaurantName.textContent = settings.restaurant_name;
        }
    }

    /**
     * Update order summary calculations
     */
    async updateOrderSummary(cartItems) {
        const settings = await this.getSettings();
        
        const subtotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const tax = this.calculateTax(subtotal);
        const total = this.calculateTotal(subtotal);

        // Update display
        const subtotalEl = document.getElementById('subtotal');
        const taxEl = document.getElementById('tax');
        const totalEl = document.getElementById('total');

        if (subtotalEl) subtotalEl.textContent = this.formatCurrency(subtotal);
        if (taxEl) taxEl.textContent = this.formatCurrency(tax);
        if (totalEl) totalEl.textContent = this.formatCurrency(total);

        return { subtotal, tax, total };
    }
}

// Global instance
window.taxSettings = new TaxSettingsManager();

// Auto-update on page load
document.addEventListener('DOMContentLoaded', function() {
    window.taxSettings.updateCartSummary();
});
