export async function fetchWithRetry(url, options = {}, retries = 3) {
  let lastError = null;

  for (let attempt = 0; attempt < retries; attempt++) {
    try {
      const response = await fetch(url, options);

      if (response.status === 429) {
        const retryAfter = parseInt(response.headers.get('Retry-After') || '5', 10);
        await delay(retryAfter * 1000);
        continue;
      }

      if (!response.ok && response.status >= 500) {
        throw new Error(`Server error: ${response.status}`);
      }

      return response;
    } catch (error) {
      if (error.name === 'AbortError') throw error;
      lastError = error;

      if (attempt < retries - 1) {
        const backoff = Math.min(1000 * Math.pow(2, attempt), 10000);
        await delay(backoff);
      }
    }
  }

  throw lastError || new Error('Request failed after retries');
}

export async function apiPostWithRetry(url, data, retries = 3) {
  return fetchWithRetry(
    url,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${localStorage.getItem('token')}`,
      },
      body: JSON.stringify(data),
    },
    retries
  );
}

export async function apiGetWithRetry(url, retries = 3) {
  return fetchWithRetry(
    url,
    {
      method: 'GET',
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token')}`,
      },
    },
    retries
  );
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
