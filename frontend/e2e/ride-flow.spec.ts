import { expect, test, type APIRequestContext, type Page } from '@playwright/test';

const RIDER = 'rider@example.com';
const DRIVER = 'driver@example.com';
const LIBERTY_MARKET = { latitude: 31.5102, longitude: 74.3441 };

async function token(request: APIRequestContext, email: string): Promise<string> {
  const response = await request.post('/api/v1/auth/tokens', { data: { email, password: 'password' } });
  expect(response.ok(), `log in ${email}`).toBeTruthy();
  return (await response.json()).token as string;
}

/** Puts the seeded accounts in a known state: no active rides, driver online near the pickup. */
async function resetDemoState(request: APIRequestContext) {
  const riderToken = await token(request, RIDER);
  const rides = await request.get('/api/v1/rides?status=active', { headers: { Authorization: `Bearer ${riderToken}` } });
  for (const ride of (await rides.json()).data as { id: string; status: string }[]) {
    if (ride.status !== 'in_progress') {
      await request.post(`/api/v1/rides/${ride.id}/cancel`, { headers: { Authorization: `Bearer ${riderToken}` } });
    }
  }

  const driverToken = await token(request, DRIVER);
  const driverRides = await request.get('/api/v1/rides?status=active', { headers: { Authorization: `Bearer ${driverToken}` } });
  for (const ride of (await driverRides.json()).data as { id: string; status: string }[]) {
    if (ride.status === 'in_progress') {
      await request.post(`/api/v1/rides/${ride.id}/complete`, { headers: { Authorization: `Bearer ${driverToken}` } });
    }
  }

  await request.put('/api/v1/driver/availability', {
    headers: { Authorization: `Bearer ${driverToken}` },
    data: { status: 'available', ...LIBERTY_MARKET },
  });
}

async function signIn(page: Page, email: string) {
  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill('password');
  await page.getByRole('button', { name: 'Sign in' }).click();
}

test('a rider books a ride and follows it while a driver accepts, starts and completes it', async ({ browser, request }, testInfo) => {
  await resetDemoState(request);

  const riderPage = await (await browser.newContext()).newPage();
  const driverPage = await (await browser.newContext()).newPage();

  await test.step('rider requests an Economy ride', async () => {
    await signIn(riderPage, RIDER);
    await expect(riderPage.getByRole('heading', { name: 'Book a ride' })).toBeVisible();

    await riderPage.getByLabel('Pickup').selectOption('Liberty Market, Gulberg III');
    await riderPage.getByLabel('Destination').selectOption('Packages Mall, Walton Road');
    await expect(riderPage.getByText(/Pickup in \d+ min/).first()).toBeVisible();
    await riderPage.screenshot({ path: testInfo.outputPath('1-rider-booking.png'), fullPage: true });

    await riderPage.getByRole('button', { name: 'Request Economy' }).click();
    await expect(riderPage.getByText('Finding your driver')).toBeVisible();
  });

  await test.step('driver sees the request and accepts it', async () => {
    await signIn(driverPage, DRIVER);
    await expect(driverPage.getByRole('heading', { name: 'Ride requests near you' })).toBeVisible();
    await driverPage.screenshot({ path: testInfo.outputPath('2-driver-offers.png'), fullPage: true });

    await driverPage.getByRole('button', { name: 'Accept ride' }).first().click();
    await expect(driverPage.getByRole('heading', { name: 'Current ride' })).toBeVisible();
  });

  await test.step('rider sees the assigned driver and ETA (via polling)', async () => {
    await expect(riderPage.getByText('Driver on the way')).toBeVisible({ timeout: 10_000 });
    await expect(riderPage.getByText('Usman Tariq')).toBeVisible();
    await expect(riderPage.getByText(/Arriving in about \d+ min/)).toBeVisible();
    await riderPage.screenshot({ path: testInfo.outputPath('3-rider-driver-assigned.png'), fullPage: true });
  });

  await test.step('driver starts and completes the trip', async () => {
    await driverPage.getByRole('button', { name: 'Start trip (rider picked up)' }).click();
    await expect(riderPage.getByText('On trip')).toBeVisible({ timeout: 10_000 });
    await expect(riderPage.getByRole('button', { name: 'Cancel ride' })).toHaveCount(0);

    await driverPage.getByRole('button', { name: 'Complete trip' }).click();
    await expect(driverPage.getByRole('heading', { name: 'Ride requests near you' })).toBeVisible();
  });

  await test.step('rider sees the completed trip with the final fare', async () => {
    await expect(riderPage.getByText('Trip completed')).toBeVisible({ timeout: 10_000 });
    await expect(riderPage.getByText('Fare', { exact: true })).toBeVisible();
    await riderPage.screenshot({ path: testInfo.outputPath('4-rider-completed.png'), fullPage: true });

    await riderPage.getByRole('button', { name: 'Book another ride' }).click();
    await expect(riderPage.getByRole('heading', { name: 'Book a ride' })).toBeVisible();
  });
});

test('a rider can cancel a ride while waiting for a driver', async ({ page, request }) => {
  await resetDemoState(request);

  await signIn(page, RIDER);
  await page.getByLabel('Pickup').selectOption('Gaddafi Stadium');
  await page.getByLabel('Destination').selectOption('Model Town Park');
  await page.getByRole('button', { name: 'Request Economy' }).click();
  await expect(page.getByText('Finding your driver')).toBeVisible();

  await page.getByRole('button', { name: 'Cancel ride' }).click();
  await page.getByRole('button', { name: 'Yes, cancel' }).click();

  await expect(page.getByText('Ride cancelled')).toBeVisible();
});
