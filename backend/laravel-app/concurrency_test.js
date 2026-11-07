import http from 'k6/http';
import { check } from 'k6';

export const options = {
  vus: 2,
  iterations: 2,
};


const tokens = [
  'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2F1dGgvbG9naW4iLCJpYXQiOjE3OTM4ODE1NzIsImV4cCI6MTc5Mzg4NTE3MiwibmJmIjoxNzkzODgxNTcyLCJqdGkiOiJva05FSnhCOEdteVZNZjFwIiwic3ViIjoiMyIsInBydiI6IjIzYmQ1Yzg5NDlmNjAwYWRiMzllNzAxYzQwMDg3MmRiN2E1OTc2ZjcifQ.HgO76upM6FxZTYF-r7HYR1G8SdDE0-_rEZbqqFCAQQI',
  'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2F1dGgvbG9naW4iLCJpYXQiOjE3OTM4ODE2NzMsImV4cCI6MTc5Mzg4NTI3MywibmJmIjoxNzkzODgxNjczLCJqdGkiOiI3TG5LOEJ2dGd6UFREdVBTIiwic3ViIjoiNCIsInBydiI6IjIzYmQ1Yzg5NDlmNjAwYWRiMzllNzAxYzQwMDg3MmRiN2E1OTc2ZjcifQ.hLQHXQITIDeotxhLAqcaZ19km1YTWBvHh4sTFzvEugw'
];

export default function () {
  const url = 'http://127.0.0.1:8000/api/orders';
  
  const payload = JSON.stringify({
    items: [
      {
        product_id: 10,
        quantity: 1
      }
    ]
  });

 
  const currentToken = tokens[__VU - 1];

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${currentToken}`,
    },
  };

  const res = http.post(url, payload, params);

  console.log(`User (VU ${__VU}) Response status: ${res.status} - Body: ${res.body}`);
}